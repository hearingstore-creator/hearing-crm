<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchMetaLead;
use App\Models\LeadCaptureForm;
use App\Models\LeadWebhookEvent;
use App\Services\LeadConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lead connectors (server-to-server). Each answers quickly and logs the call; the lead itself goes through
 * the same processing as a website form submission.
 *
 *   GET|POST /api/v1/hooks/meta              Meta app webhook: Lead Ads (leadgen) and WhatsApp Cloud API messages
 *   POST     /api/v1/hooks/google/{formKey}  Google Ads lead form extension (key = the capture form's secret)
 *   POST     /api/v1/hooks/lead/{formKey}    Any other source: Zapier, Make, landing pages, missed-call services
 *                                            (token = the capture form's secret, as ?token= or X-Webhook-Token)
 */
class LeadHookController extends Controller
{
    public function __construct(private LeadConnector $connector)
    {
    }

    /** Meta's one-time check when the webhook is added in the Meta app. */
    public function metaVerify(Request $request): Response
    {
        $token = (string) config('connectors.meta.verify_token');
        abort_if($token === '', 404);
        abort_unless($request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function meta(Request $request): JsonResponse
    {
        $secret = (string) config('connectors.meta.app_secret');
        abort_if($secret === '', 404);
        $signature = (string) $request->header('X-Hub-Signature-256');
        abort_unless($signature !== '' && hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secret), $signature), 403);

        $body = $request->json()->all();
        foreach ((array) ($body['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                if (($change['field'] ?? '') === 'leadgen' && !empty($value['leadgen_id'])) {
                    $id = (string) $value['leadgen_id'];
                    if (LeadWebhookEvent::where('source', 'meta')->where('external_id', $id)->whereIn('status', ['processed', 'duplicate'])->exists()) {
                        continue;   // Meta sends again when unsure; it was handled
                    }
                    $event = LeadWebhookEvent::create(['source' => 'meta', 'external_id' => $id, 'payload' => $value]);
                    FetchMetaLead::dispatch($event->id);
                }

                if (($change['field'] ?? '') === 'messages') {
                    $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [(string) ($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);
                    foreach ((array) ($value['messages'] ?? []) as $message) {
                        $event = LeadWebhookEvent::create([
                            'source' => 'whatsapp', 'external_id' => $message['id'] ?? null,
                            'payload' => ['message' => $message, 'profile_name' => $names[(string) ($message['from'] ?? '')] ?? null],
                        ]);
                        $this->safely($event, fn () => $this->connector->whatsappMessage($event, $message, $names[(string) ($message['from'] ?? '')] ?? null));
                    }
                }
            }
        }

        return response()->json(['ok' => true]);   // Meta only needs a quick 200
    }

    public function google(Request $request, string $formKey): JsonResponse
    {
        $form = LeadCaptureForm::where('key', $formKey)->where('is_active', true)->first();
        abort_unless($form, 404);
        abort_unless(hash_equals((string) $form->secret, (string) $request->input('google_key')), 403);

        $payload = $request->json()->all() ?: $request->all();
        unset($payload['google_key']);
        $event = LeadWebhookEvent::create(['source' => 'google', 'external_id' => $payload['lead_id'] ?? null, 'payload' => $payload, 'form_id' => $form->id]);
        $this->safely($event, fn () => $this->connector->googleLead($event, $form, $payload));

        return response()->json((object) []);   // Google expects 200 with an empty JSON body
    }

    public function generic(Request $request, string $formKey): JsonResponse
    {
        $form = LeadCaptureForm::where('key', $formKey)->where('is_active', true)->first();
        abort_unless($form, 404);
        $token = (string) ($request->header('X-Webhook-Token') ?: $request->query('token', ''));
        abort_unless($token !== '' && hash_equals((string) $form->secret, $token), 403);

        $payload = $request->except(['token']);
        $event = LeadWebhookEvent::create(['source' => 'webhook', 'external_id' => isset($payload['id']) && is_scalar($payload['id']) ? (string) $payload['id'] : null, 'payload' => $payload, 'form_id' => $form->id]);
        $status = $this->safely($event, fn () => $this->connector->genericLead($event, $form, $payload));

        return response()->json(['ok' => $status !== 'failed', 'status' => $status, 'message' => $event->fresh()->note], $status === 'failed' ? 422 : 202);
    }

    /** A connector problem is logged on the event, never thrown back at the sender. */
    private function safely(LeadWebhookEvent $event, callable $fn): string
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);
            $event->mark('failed', 'Error: ' . $e->getMessage());

            return 'failed';
        }
    }
}
