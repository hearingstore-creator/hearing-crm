<?php

namespace App\Http\Controllers\Api;

use App\Enums\InquiryType;
use App\Http\Controllers\Controller;
use App\Models\LeadCaptureForm;
use App\Services\WebLeadIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

/**
 * POST /api/v1/leads – called by the website's SERVER (never from the browser).
 *
 * Headers: X-Form-Key, X-Timestamp (unix seconds), X-Signature = hex HMAC-SHA256("{timestamp}.{raw body}", secret)
 * Body (JSON): name, phone, email?, message?, inquiry_for[]?, utm_source?, utm_medium?, utm_campaign?, utm_content?, utm_term?,
 *               gclid?, fbclid?, landing_page?, referrer_url?, page_url?,
 *              client_ip, user_agent?, elapsed_seconds, website (honeypot, must be empty), turnstile_token?
 *
 * Bots get a normal 202 when caught by honeypot / timing / content / blocklist, so they learn nothing;
 * those posts are kept in the inbox as "spam" and can be restored.
 */
class WebLeadController extends Controller
{
    public function __construct(private WebLeadIntake $intake)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->authenticate($request);
        if ($form instanceof JsonResponse) {
            return $form;
        }

        $v = Validator::make($request->all(), [
            'name'            => ['required', 'string', 'min:2', 'max:191'],
            'phone'           => ['required', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:191'],
            'message'         => ['nullable', 'string', 'max:2000'],
            'inquiry_for'     => ['nullable', 'array', 'max:10'],
            'inquiry_for.*'   => [new Enum(InquiryType::class)],
            'utm_source'      => ['nullable', 'string', 'max:100'],
            'utm_medium'      => ['nullable', 'string', 'max:100'],
            'utm_campaign'    => ['nullable', 'string', 'max:150'],
            'utm_content'     => ['nullable', 'string', 'max:150'],
            'utm_term'        => ['nullable', 'string', 'max:150'],
            'gclid'           => ['nullable', 'string', 'max:255'],
            'fbclid'          => ['nullable', 'string', 'max:255'],
            'landing_page'    => ['nullable', 'string', 'max:500'],
            'referrer_url'    => ['nullable', 'string', 'max:500'],
            'consent'         => ['nullable', 'boolean'],
            'page_url'        => ['nullable', 'string', 'max:500'],
            'client_ip'       => ['required', 'ip'],
            'user_agent'      => ['nullable', 'string', 'max:500'],
            'elapsed_seconds' => ['required', 'numeric', 'min:0'],
            'website'         => ['nullable', 'string', 'max:255'],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => 'Invalid data.', 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();

        $phone = $this->intake->phone($data['phone']);
        if (!$phone) {
            return response()->json(['ok' => false, 'message' => (\App\Support\Region::isBangladesh() ? 'Please enter a valid Bangladeshi mobile number.' : 'Please enter a valid mobile number.'), 'errors' => ['phone' => ['Invalid mobile number.']]], 422);
        }

        if ($wait = $this->intake->rateLimited($form, $data['client_ip'], $phone)) {
            return response()->json(['ok' => false, 'message' => 'Too many requests. Please try again later.'], 429)->header('Retry-After', $wait);
        }

        if (!$this->intake->turnstileOk($data['turnstile_token'] ?? null, $data['client_ip'])) {
            return response()->json(['ok' => false, 'message' => 'Verification failed. Please try again.'], 422);
        }

        $submission = $this->intake->accept($form, $data, $phone);

        return response()->json(['ok' => true, 'reference' => $submission->id], 202);
    }

    /** Form key + fresh timestamp + valid HMAC signature over the raw body. */
    private function authenticate(Request $request): LeadCaptureForm|JsonResponse
    {
        return $this->intake->signedForm($request)
            ?? response()->json(['ok' => false, 'message' => 'Unauthorized.'], 401);
    }
}
