<?php

namespace App\Services;

use App\Models\LeadCaptureForm;
use App\Models\LeadSubmission;
use App\Models\LeadWebhookEvent;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Turns what Meta, WhatsApp, Google or a webhook sends into an inbox submission, which then goes through
 * the same steps as a website lead (duplicate check by phone, deal, SLA call task, campaign).
 * Every call is kept in lead_webhook_events with its outcome; a call that comes twice is recognised by its id.
 */
class LeadConnector
{
    public function __construct(private WebLeadIntake $intake)
    {
    }

    /** The capture form (branch, SLA, default interest) that Meta / WhatsApp leads use. */
    public function metaForm(bool $whatsapp = false): ?LeadCaptureForm
    {
        $key = $whatsapp ? (config('connectors.meta.whatsapp_form_key') ?: config('connectors.meta.form_key')) : config('connectors.meta.form_key');

        return $key ? LeadCaptureForm::where('key', $key)->where('is_active', true)->first() : null;
    }

    /**
     * @param array{name?: ?string, phone?: ?string, email?: ?string, message?: ?string} $person
     * @param array<string, ?string> $tracking utm_source, utm_medium, utm_campaign, utm_content, utm_term, gclid, fbclid, landing_page
     */
    public function submit(LeadWebhookEvent $event, LeadCaptureForm $form, string $source, array $person, array $tracking = [], ?string $externalId = null): string
    {
        if ($externalId && LeadSubmission::where('source', $source)->where('external_id', $externalId)->exists()) {
            $event->mark('duplicate', 'Already received earlier');

            return 'duplicate';
        }

        $raw = trim((string) ($person['phone'] ?? ''));
        $phone = $raw !== '' ? $this->intake->phone($raw) : null;
        if (!$phone) {
            $event->mark('failed', $raw === '' ? 'No phone number in the lead.' : "Not a mobile number this CRM can use: {$raw}");

            return 'failed';
        }

        $name = trim((string) ($person['name'] ?? '')) ?: ucfirst($source) . ' lead ' . substr($phone, -4);
        $submission = $this->intake->accept($form, array_filter([
            'name'            => mb_substr(strip_tags($name), 0, 191),
            'phone'           => $raw,
            'email'           => filter_var($person['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
            'message'         => isset($person['message']) ? mb_substr((string) $person['message'], 0, 2000) : null,
            'source'          => $source,
            'external_id'     => $externalId,
            'client_ip'       => request()?->ip() ?? '0.0.0.0',
            'user_agent'      => mb_substr(LeadWebhookEvent::SOURCES[$event->source] ?? $event->source, 0, 500),
            'elapsed_seconds' => 9999,   // not a typed form: the "too fast" spam check does not apply
        ] + array_map(fn ($v) => $v !== null ? mb_substr((string) $v, 0, 500) : null, $tracking), fn ($v) => $v !== null && $v !== ''), $phone);

        $status = $submission->status === LeadSubmission::SPAM ? 'ignored' : 'processed';
        $event->mark($status, $submission->status === LeadSubmission::SPAM ? 'Spam: ' . $submission->spam_reason : null, $submission->id);
        $event->update(['form_id' => $form->id]);

        return $status;
    }

    /**
     * The campaign an ad platform's campaign id belongs to. Unknown ids get a campaign of their own,
     * so ad leads are counted per campaign from the first day (rename it in Marketing → Campaigns).
     */
    public function campaignFor(string $channel, ?string $externalId, ?string $name): ?MarketingCampaign
    {
        $externalId = $externalId ? trim($externalId) : null;
        if (!$externalId && !$name) {
            return null;
        }
        if ($externalId && ($c = MarketingCampaign::where('external_id', $externalId)->first())) {
            return $c;
        }
        if ($name && ($c = MarketingCampaign::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first())) {
            if ($externalId && !$c->external_id) {
                $c->update(['external_id' => $externalId]);
            }

            return $c;
        }

        $label = $name ?: ucfirst($channel) . ' campaign ' . $externalId;
        $code = MarketingCampaign::normalizeCode(Str::slug($label, '_')) ?: $channel . '_' . $externalId;
        if (MarketingCampaign::where('code', $code)->exists()) {
            $code .= '_' . ($externalId ?: Str::random(4));
        }

        return MarketingCampaign::create([
            'name' => mb_substr($label, 0, 150), 'code' => mb_substr($code, 0, 150), 'channel' => $channel,
            'external_id' => $externalId, 'status' => 'active',
            'notes' => 'Created automatically from the first lead of this ad campaign.',
        ]);
    }

    // ── Meta Lead Ads ────────────────────────────────────────────────────

    /** Read one lead from the Graph API (the webhook only says which lead) and submit it. */
    public function fetchMetaLead(LeadWebhookEvent $event): string
    {
        $leadgenId = $event->external_id;
        $form = $this->metaForm();
        if (!$form) {
            $event->mark('failed', 'Choose the capture form for Meta leads (META_LEAD_FORM_KEY).');
            return 'failed';
        }
        if (!config('connectors.meta.page_token')) {
            $event->mark('failed', 'META_PAGE_TOKEN is not set, so the lead details cannot be read.');
            return 'failed';
        }

        $response = Http::timeout(15)->get('https://graph.facebook.com/' . config('connectors.meta.graph_version') . '/' . $leadgenId, [
            'access_token' => config('connectors.meta.page_token'),
            'fields' => 'field_data,campaign_id,campaign_name,ad_id,ad_name,adset_name,form_id,platform,created_time,is_organic',
        ]);
        if (!$response->successful()) {
            $event->mark('failed', 'Meta did not return the lead: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
            return 'failed';
        }
        $lead = $response->json();

        $fields = [];
        foreach ((array) ($lead['field_data'] ?? []) as $f) {
            $fields[mb_strtolower((string) ($f['name'] ?? ''))] = implode(', ', (array) ($f['values'] ?? []));
        }
        $name = $fields['full_name'] ?? trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? ''));
        $phone = $fields['phone_number'] ?? $fields['phone'] ?? $fields['mobile_number'] ?? null;
        $answers = collect($fields)->except(['full_name', 'first_name', 'last_name', 'phone_number', 'phone', 'mobile_number', 'email'])
            ->map(fn ($v, $k) => str_replace('_', ' ', ucfirst($k)) . ': ' . $v)->implode("\n");

        $channel = ($lead['platform'] ?? '') === 'ig' ? 'instagram' : 'facebook';
        $campaign = empty($lead['is_organic']) ? $this->campaignFor($channel, $lead['campaign_id'] ?? null, $lead['campaign_name'] ?? null) : null;

        return $this->submit($event, $form, $channel, [
            'name' => $name, 'phone' => $phone, 'email' => $fields['email'] ?? null, 'message' => $answers ?: null,
        ], [
            'utm_source' => $channel, 'utm_medium' => 'lead_ad', 'utm_campaign' => $campaign?->code,
            'utm_content' => $lead['ad_name'] ?? null, 'utm_term' => $lead['adset_name'] ?? null,
        ], $leadgenId);
    }

    // ── WhatsApp (Cloud API) ─────────────────────────────────────────────

    /** A first message from a number (or one after a quiet spell) opens an enquiry; replies inside the window do not. */
    public function whatsappMessage(LeadWebhookEvent $event, array $message, ?string $profileName): string
    {
        $form = $this->metaForm(true);
        if (!$form) {
            $event->mark('failed', 'Choose the capture form for WhatsApp leads (WHATSAPP_LEAD_FORM_KEY or META_LEAD_FORM_KEY).');
            return 'failed';
        }

        $from = (string) ($message['from'] ?? '');
        $phone = $this->intake->phone($from);
        app(SequenceService::class)->stopForPhone($from, 'Replied on WhatsApp');
        $hours = max(1, (int) config('connectors.meta.whatsapp_repeat_hours', 24));
        if ($phone && LeadSubmission::where('source', 'whatsapp')->where('normalized_phone', $phone)->where('created_at', '>=', now()->subHours($hours))->exists()) {
            $event->mark('ignored', "Same WhatsApp conversation (within {$hours} h)");
            return 'ignored';
        }

        $text = match ($message['type'] ?? 'text') {
            'text' => $message['text']['body'] ?? '',
            'button' => $message['button']['text'] ?? '',
            'interactive' => $message['interactive']['button_reply']['title'] ?? ($message['interactive']['list_reply']['title'] ?? ''),
            default => '[' . ($message['type'] ?? 'message') . ']',
        };

        // Click-to-WhatsApp ads tell which ad the chat came from
        $ref = (array) ($message['referral'] ?? []);
        $tracking = ['utm_source' => 'whatsapp', 'utm_medium' => $ref ? 'ctwa' : 'chat'];
        if ($ref) {
            $tracking += [
                'utm_content' => $ref['headline'] ?? null,
                'fbclid' => $ref['ctwa_clid'] ?? null,
                'landing_page' => $ref['source_url'] ?? null,
            ];
            $campaign = $this->campaignFor('whatsapp', $ref['source_id'] ?? null, $ref['headline'] ?? null);
            $tracking['utm_campaign'] = $campaign?->code;
        }

        return $this->submit($event, $form, 'whatsapp', ['name' => $profileName, 'phone' => $from, 'message' => $text], $tracking, $message['id'] ?? null);
    }

    // ── Google Ads lead form extension ───────────────────────────────────

    public function googleLead(LeadWebhookEvent $event, LeadCaptureForm $form, array $payload): string
    {
        if (!empty($payload['is_test'])) {
            $event->mark('ignored', 'Google test lead – the connection works.');
            return 'ignored';
        }

        $cols = [];
        $extra = [];
        foreach ((array) ($payload['user_column_data'] ?? []) as $c) {
            $id = strtoupper((string) ($c['column_id'] ?? ''));
            $value = (string) ($c['string_value'] ?? '');
            $cols[$id] = $value;
            if (!in_array($id, ['FULL_NAME', 'FIRST_NAME', 'LAST_NAME', 'PHONE_NUMBER', 'EMAIL'], true) && $value !== '') {
                $extra[] = ($c['column_name'] ?? $id) . ': ' . $value;
            }
        }
        $campaign = $this->campaignFor('google', isset($payload['campaign_id']) ? (string) $payload['campaign_id'] : null, null);

        return $this->submit($event, $form, 'google', [
            'name' => $cols['FULL_NAME'] ?? trim(($cols['FIRST_NAME'] ?? '') . ' ' . ($cols['LAST_NAME'] ?? '')),
            'phone' => $cols['PHONE_NUMBER'] ?? null,
            'email' => $cols['EMAIL'] ?? null,
            'message' => $extra ? implode("\n", $extra) : null,
        ], [
            'utm_source' => 'google', 'utm_medium' => 'lead_form', 'utm_campaign' => $campaign?->code,
            'gclid' => $payload['gcl_id'] ?? null,
        ], isset($payload['lead_id']) ? (string) $payload['lead_id'] : null);
    }

    // ── general webhook (Zapier, Make, landing-page builders, missed-call services) ──

    public function genericLead(LeadWebhookEvent $event, LeadCaptureForm $form, array $p): string
    {
        $pick = function (array $keys) use ($p) {
            foreach ($keys as $k) {
                if (isset($p[$k]) && is_scalar($p[$k]) && trim((string) $p[$k]) !== '') {
                    return trim((string) $p[$k]);
                }
            }
            return null;
        };

        $phone = $pick(['phone', 'mobile', 'phone_number', 'msisdn', 'caller', 'caller_id', 'from', 'contact']);
        $name = $pick(['name', 'full_name', 'fullname']) ?? trim(($pick(['first_name', 'firstname']) ?? '') . ' ' . ($pick(['last_name', 'lastname']) ?? ''));
        $sourceIn = mb_strtolower((string) $pick(['source', 'channel']));
        $source = \App\Enums\SourceOfEntry::tryFrom($sourceIn)?->value
            ?? (in_array($sourceIn, ['missed_call', 'call', 'ivr'], true) ? 'phone_call' : 'webhook');
        if ($source === 'phone_call' && !$name) {
            $name = 'Missed call ' . substr((string) $phone, -4);
        }

        return $this->submit($event, $form, $source, [
            'name' => $name, 'phone' => $phone, 'email' => $pick(['email', 'mail']),
            'message' => $pick(['message', 'notes', 'note', 'comments', 'question']),
        ], [
            'utm_source' => $pick(['utm_source']), 'utm_medium' => $pick(['utm_medium']), 'utm_campaign' => $pick(['utm_campaign', 'campaign']),
            'utm_content' => $pick(['utm_content']), 'utm_term' => $pick(['utm_term']),
            'gclid' => $pick(['gclid']), 'fbclid' => $pick(['fbclid']), 'landing_page' => $pick(['page_url', 'landing_page', 'url']),
        ], $pick(['external_id', 'id', 'lead_id', 'call_id']));
    }

    /** Process a logged call again (from the Connections tab). */
    public function replay(LeadWebhookEvent $event): string
    {
        $payload = (array) $event->payload;

        return match ($event->source) {
            'meta' => $this->fetchMetaLead($event),
            'whatsapp' => $this->whatsappMessage($event, (array) ($payload['message'] ?? []), $payload['profile_name'] ?? null),
            'google' => ($form = LeadCaptureForm::find($event->form_id)) ? $this->googleLead($event, $form, $payload) : tap('failed', fn () => $event->mark('failed', 'The capture form is gone.')),
            default => ($form = LeadCaptureForm::find($event->form_id)) ? $this->genericLead($event, $form, $payload) : tap('failed', fn () => $event->mark('failed', 'The capture form is gone.')),
        };
    }
}
