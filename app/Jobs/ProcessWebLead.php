<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\SourceOfEntry;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadSubmission;
use App\Services\DealService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns a website submission into Person + Deal (stage "New", default owner) with a "call within SLA" activity.
 * Same phone already in the CRM: never a second person –
 *   - an open deal exists  → the enquiry is added to it (note + call), submission marked "duplicate";
 *   - no open deal         → a new deal is opened for that person.
 */
class ProcessWebLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public int $submissionId)
    {
    }

    public function handle(DealService $deals): void
    {
        DB::transaction(function () use ($deals) {
            $s = LeadSubmission::with('form')->lockForUpdate()->find($this->submissionId);
            if (!$s || $s->status !== LeadSubmission::PENDING) {
                return;
            }

            $form = $s->form;
            $sla = $form->sla_minutes ?? 15;
            $inquiry = $s->inquiry_for ?: ($form->default_inquiry ?? null);
            $vendorId = $form->vendor_id ?? null;
            // The campaign whose code is in the link (utm_campaign / QR), if it is set up in Marketing → Campaigns
            $campaign = \App\Models\MarketingCampaign::findByCode($s->utm_campaign);
            $touch = array_filter([
                'campaign_id'  => $campaign?->id,
                'utm_source'   => $s->utm_source,
                'utm_medium'   => $s->utm_medium,
                'utm_campaign' => $s->utm_campaign,
                'utm_content'  => $s->utm_content,
                'utm_term'     => $s->utm_term,
                'gclid'        => $s->gclid,
                'fbclid'       => $s->fbclid,
                'landing_page' => $s->landing_page ?: $s->page_url,
            ], fn ($v) => $v !== null && $v !== '');
            // website form, or a connector (facebook, instagram, google, whatsapp, phone_call …; anything else = other)
            $source = $s->source ? (SourceOfEntry::tryFrom($s->source) ?? SourceOfEntry::OTHER) : SourceOfEntry::WEBSITE;
            $label = $s->source ? $source->label() . ($s->external_id && $s->source !== 'whatsapp' ? ' lead form' : '') : 'Website';
            $dealAttributes = array_filter([
                'source'       => $source->value,
                'inquiry_for'  => $inquiry,
            ], fn ($v) => $v !== null && $v !== []) + $touch;
            // first touch of the person (kept as it was when the lead already has one)
            $firstTouch = $touch + array_filter(['referrer_url' => $s->referrer_url], fn ($v) => $v !== null && $v !== '');

            $lead = Lead::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('normalized_phone', $s->normalized_phone)->oldest('id')->first();
            $status = LeadSubmission::PROCESSED;

            if ($lead) {
                if (!$lead->campaign_id && !$lead->utm_source && !$lead->utm_campaign && $firstTouch) {
                    $lead->forceFill($firstTouch)->saveQuietly();
                }
                $deal = Deal::withoutGlobalScopes()->whereNull('deleted_at')->open()
                    ->where('lead_id', $lead->id)->latest('id')->first();
                if ($deal) {
                    $status = LeadSubmission::DUPLICATE;
                    $deals->addActivity($deal, ActivityType::NOTE, null, $this->noteText($s, $label . ' enquiry again'));
                } else {
                    $deal = $deals->openNewDeal($lead, $dealAttributes + ['vendor_id' => $vendorId ?? $lead->vendor_id]);
                    $deals->addActivity($deal, ActivityType::NOTE, null, $this->noteText($s, $label . ' enquiry'));
                }
            } else {
                $lead = Lead::create([
                    'lead_uid'        => Lead::generateUid(),
                    'full_name'       => $s->name,
                    'phone_number'    => $s->phone,
                    'email'           => $this->freeEmail($s->email),
                    'vendor_id'       => $vendorId,
                    'inquiry_for'     => $inquiry,
                    'notes'           => $s->message,
                    'source_of_entry' => $source->value,
                    'lead_status'     => LeadStatus::NEW->value,
                    'lead_type'       => LeadType::NEW_CUSTOMER->value,
                ] + $firstTouch);
                $deal = $deals->openInitialDeal($lead, $dealAttributes);
                app(\App\Services\SmsAutomationService::class)->fireAfterCommit(\App\Enums\SmsAutomationTrigger::LEAD_CREATED,
                    $lead->phone_number, 'lead', $lead->id, ['name' => $lead->full_name, 'customer_name' => $lead->full_name], 'lead-welcome:' . $lead->id);
            }

            // First-contact deadline (SLA) of an inbound lead: alerts and, by rule, reassignment run from it
            $due = DealService::dueInOfficeHours($sla);
            if (!$deal->first_contact_due_at && !$deal->first_contact_at) {
                $deal->forceFill(['first_contact_due_at' => $due])->saveQuietly();
            }
            $deals->addActivity($deal, ActivityType::CALL, $due->toDateTimeString(),
                "{$label} lead – call within {$sla} min" . ($s->page_url ? ' (' . $s->page_url . ')' : ''));

            $s->update(['status' => $status, 'lead_id' => $lead->id, 'deal_id' => $deal->id, 'error' => null, 'processed_at' => now()]);
        });
    }

    public function failed(Throwable $e): void
    {
        LeadSubmission::whereKey($this->submissionId)->update(['status' => LeadSubmission::FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }

    private function noteText(LeadSubmission $s, string $title): string
    {
        return trim($title . ($s->message ? ': ' . $s->message : '') . ($s->utm_campaign ? ' [campaign: ' . $s->utm_campaign . ']' : ''));
    }

    /** Lead emails are unique in the lead form; skip an email that already belongs to another person. */
    private function freeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        return Lead::withoutGlobalScopes()->whereNull('deleted_at')->where('email', $email)->exists() ? null : $email;
    }
}
