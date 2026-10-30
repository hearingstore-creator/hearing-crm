<?php

namespace App\Http\Controllers;

use App\Enums\InquiryType;
use App\Models\LeadCaptureForm;
use App\Models\Setting;
use App\Services\WebLeadIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Hosted lead form for any website: open /f/{key} directly, or add /f/{key}/embed.js to show it as a popup.
 *
 * No secret ever reaches the browser. The page carries a signed time token, so the fill-time check
 * cannot be faked; honeypot, rate limits, blocklist and processing are shared with the signed API.
 */
class PublicLeadFormController extends Controller
{
    private const TOKEN_MAX_AGE = 7200;

    /** The short list of interests shown on the form (submissions may still send any InquiryType). */
    private const CHOICES = [InquiryType::HEARING_TEST, InquiryType::HEARING_AID, InquiryType::REPAIR, InquiryType::OTHER];

    public function __construct(private WebLeadIntake $intake)
    {
    }

    public function show(Request $request, string $key): View
    {
        $form = $this->form($key);
        $now = time();

        return view('public.lead-form', [
            'form'      => $form,
            'company'   => Setting::find(1)?->company_name ?: config('app.name'),
            'token'     => $now . '.' . $this->sign($form, $now),
            'inquiries' => self::CHOICES,
            'preset'    => $form->default_inquiry ?? [],
            'embedded'  => $request->boolean('embed'),
            'turnstile' => config('weblead.turnstile_site_key'),
        ]);
    }

    public function submit(Request $request, string $key): JsonResponse
    {
        $form = $this->form($key);

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
            'page_url'        => ['nullable', 'string', 'max:500'],
            'website'         => ['nullable', 'string', 'max:255'],
            't'               => ['required', 'string', 'max:100'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
        ], [
            'name.required'  => 'Please enter your name.',
            'phone.required' => 'Please enter your mobile number.',
            'email.email'    => 'Please enter a valid email address.',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();

        $issued = $this->issuedAt($form, $data['t']);
        if ($issued === null) {
            return response()->json(['ok' => false, 'message' => 'This form has expired. Please reload the page and try again.'], 422);
        }

        $phone = $this->intake->phone($data['phone']);
        if (!$phone) {
            return response()->json(['ok' => false, 'message' => 'Please enter a valid Bangladeshi mobile number (e.g. 017XXXXXXXX).', 'errors' => ['phone' => ['Invalid mobile number.']]], 422);
        }

        $ip = (string) $request->ip();
        if ($this->intake->rateLimited($form, $ip, $phone)) {
            return response()->json(['ok' => false, 'message' => 'Too many requests. Please try again later.'], 429);
        }
        if (!$this->intake->turnstileOk($data['cf-turnstile-response'] ?? null, $ip)) {
            return response()->json(['ok' => false, 'message' => 'Verification failed. Please try again.'], 422);
        }

        $this->intake->accept($form, $data + [
            'client_ip'       => $ip,
            'user_agent'      => mb_substr((string) $request->userAgent(), 0, 500),
            'elapsed_seconds' => time() - $issued,
        ], $phone);

        return response()->json(['ok' => true]);
    }

    /** Popup loader for the website: adds a floating button and opens the form in a modal iframe. */
    public function embed(string $key): Response
    {
        $form = $this->form($key);
        $js = view('public.lead-form-embed', ['url' => $form->publicUrl() . '?embed=1'])->render();

        return response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function form(string $key): LeadCaptureForm
    {
        return LeadCaptureForm::findPublic($key);
    }

    private function sign(LeadCaptureForm $form, int $time): string
    {
        return hash_hmac('sha256', $form->key . '|' . $time, config('app.key'));
    }

    /** Unix time the page was served, or null when the token is forged or too old. */
    private function issuedAt(LeadCaptureForm $form, string $token): ?int
    {
        [$time, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if (!ctype_digit($time) || !hash_equals($this->sign($form, (int) $time), $sig)) {
            return null;
        }

        return time() - (int) $time <= self::TOKEN_MAX_AGE ? (int) $time : null;
    }
}
