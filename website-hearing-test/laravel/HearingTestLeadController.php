<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Website side: receives the hearing-test lead from the browser and forwards it,
 * server-to-server, to the CRM's POST /api/v1/leads (HMAC signed). The secret never
 * reaches the browser.
 *
 * Route (website routes/web.php):
 *   Route::post('hearing-test/lead', [HearingTestLeadController::class, 'store'])
 *       ->name('hearing-test.lead')->middleware('throttle:5,1');
 */
class HearingTestLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'min:2', 'max:100'],
            'phone'           => ['required', 'string', 'max:20'],
            'website'         => ['nullable', 'string', 'max:255'],   // honeypot, the CRM rejects non-empty
            'consent'         => ['nullable', 'boolean'],
            'elapsed_seconds' => ['required', 'numeric', 'min:0'],
            'summary'         => ['required', 'string', 'max:1500'],
            'page_url'        => ['nullable', 'string', 'max:500'],
            'referrer_url'    => ['nullable', 'string', 'max:500'],
            'utm_source'      => ['nullable', 'string', 'max:100'],
            'utm_medium'      => ['nullable', 'string', 'max:100'],
            'utm_campaign'    => ['nullable', 'string', 'max:150'],
            'utm_content'     => ['nullable', 'string', 'max:150'],
            'utm_term'        => ['nullable', 'string', 'max:150'],
            'gclid'           => ['nullable', 'string', 'max:255'],
            'fbclid'          => ['nullable', 'string', 'max:255'],
        ]);

        $payload = array_filter([
            'name'            => $data['name'],
            'phone'           => $data['phone'],
            'message'         => $data['summary'],
            'inquiry_for'     => ['hearing_test'],
            'consent'         => (bool) ($data['consent'] ?? false),
            'page_url'        => $data['page_url'] ?? null,
            'landing_page'    => $data['page_url'] ?? null,
            'referrer_url'    => $data['referrer_url'] ?? null,
            'utm_source'      => $data['utm_source'] ?? null,
            'utm_medium'      => $data['utm_medium'] ?? null,
            'utm_campaign'    => $data['utm_campaign'] ?? null,
            'utm_content'     => $data['utm_content'] ?? null,
            'utm_term'        => $data['utm_term'] ?? null,
            'gclid'           => $data['gclid'] ?? null,
            'fbclid'          => $data['fbclid'] ?? null,
            'client_ip'       => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 500),
            'elapsed_seconds' => $data['elapsed_seconds'],
            'website'         => $data['website'] ?? '',
        ], fn ($v) => $v !== null);

        $body = json_encode($payload);
        $ts = (string) time();

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Form-Key'  => config('hearing_test.crm_form_key'),
                    'X-Timestamp' => $ts,
                    'X-Signature' => hash_hmac('sha256', $ts . '.' . $body, (string) config('hearing_test.crm_secret')),
                    'Accept'      => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post(rtrim(config('hearing_test.crm_url'), '/') . '/api/v1/leads');
        } catch (\Throwable $e) {
            Log::warning('Hearing test lead: CRM unreachable', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false], 502);
        }

        if (!$response->successful()) {
            Log::warning('Hearing test lead: CRM rejected', ['status' => $response->status(), 'body' => $response->json()]);

            return response()->json(['ok' => false], $response->status() === 422 ? 422 : 502);
        }

        return response()->json(['ok' => true]);
    }
}
