<?php

namespace App\Services;

use App\Classes\Helper;
use App\Jobs\ProcessWebLead;
use App\Models\BlockedContact;
use App\Models\LeadCaptureForm;
use App\Models\LeadSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared intake for website leads: the signed server API (/api/v1/leads) and the hosted popup form (/f/{key}).
 * Bots caught by honeypot / timing / content / blocklist still get a normal "thank you"; their posts are kept
 * in the inbox as spam and can be restored.
 */
class WebLeadIntake
{
    /** Bangladesh mobile only: 01[3-9]XXXXXXXX (any of 017…, +88017…, 88017…). Returns 8801… or null. */
    public function phone(string $raw): ?string
    {
        $phone = Helper::normalizePhone($raw);

        return $phone && preg_match('/^8801[3-9]\d{8}$/', $phone) ? $phone : null;
    }

    /** Seconds to wait if a limit is hit, otherwise null (and the attempt is counted). */
    public function rateLimited(LeadCaptureForm $form, string $ip, string $phone): ?int
    {
        $limits = [
            ['wl:ip:' . $ip, config('weblead.per_ip_per_minute'), 60],
            ['wl:phone:' . $phone, config('weblead.per_phone_per_day'), 86400],
            ['wl:form:' . $form->id, config('weblead.per_form_per_hour'), 3600],
        ];
        foreach ($limits as [$k, $max]) {
            if (RateLimiter::tooManyAttempts($k, $max)) {
                return RateLimiter::availableIn($k);
            }
        }
        foreach ($limits as [$k, , $decay]) {
            RateLimiter::hit($k, $decay);
        }

        return null;
    }

    public function turnstileOk(?string $token, string $ip): bool
    {
        if (!config('weblead.turnstile_secret')) {
            return true;
        }
        if (!$token) {
            return false;
        }
        try {
            return (bool) Http::asForm()->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('weblead.turnstile_secret'), 'response' => $token, 'remoteip' => $ip,
                ])->json('success');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Store the submission and queue it for processing (or park it as spam). */
    public function accept(LeadCaptureForm $form, array $data, string $phone): LeadSubmission
    {
        $submission = LeadSubmission::create([
            'form_id'          => $form->id,
            'status'           => LeadSubmission::PENDING,
            'spam_reason'      => $this->spamReason($data, $phone),
            'name'             => strip_tags($data['name']),
            'phone'            => $data['phone'],
            'normalized_phone' => $phone,
            'email'            => $data['email'] ?? null,
            'message'          => isset($data['message']) ? strip_tags($data['message']) : null,
            'inquiry_for'      => $data['inquiry_for'] ?? null,
            'utm_source'       => $data['utm_source'] ?? null,
            'utm_medium'       => $data['utm_medium'] ?? null,
            'utm_campaign'     => $data['utm_campaign'] ?? null,
            'page_url'         => $data['page_url'] ?? null,
            'ip'               => $data['client_ip'],
            'user_agent'       => $data['user_agent'] ?? null,
        ]);
        $form->forceFill(['last_used_at' => now()])->saveQuietly();

        if ($submission->spam_reason) {
            $submission->update(['status' => LeadSubmission::SPAM]);
        } else {
            ProcessWebLead::dispatch($submission->id);
        }

        return $submission;
    }

    /** Honeypot, timing, content filter and blocklist. Returns why it is spam, or null. */
    private function spamReason(array $data, string $phone): ?string
    {
        if (!empty($data['website'])) {
            return 'Honeypot filled';
        }
        if ((float) $data['elapsed_seconds'] < config('weblead.min_fill_seconds')) {
            return 'Submitted too fast';
        }
        $links = preg_match_all('~(https?://|www\.)~i', ($data['message'] ?? '') . ' ' . $data['name']);
        if ($links > config('weblead.max_links') || preg_match('~(https?://|www\.)~i', $data['name'])) {
            return 'Links in message';
        }
        if ($type = BlockedContact::match($phone, $data['email'] ?? null, $data['client_ip'])) {
            return 'Blocked ' . $type;
        }

        return null;
    }
}
