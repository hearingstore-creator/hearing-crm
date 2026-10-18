<?php

/**
 * Website lead capture (POST /api/v1/leads). Values can be overridden in .env.
 */
return [
    // Signed requests older/newer than this are rejected (replay protection)
    'timestamp_tolerance' => (int) env('WEBLEAD_TIMESTAMP_TOLERANCE', 300),

    // A human needs at least this long to fill the form (bots submit instantly)
    'min_fill_seconds' => (int) env('WEBLEAD_MIN_FILL_SECONDS', 3),

    // Rate limits
    'per_ip_per_minute'   => (int) env('WEBLEAD_PER_IP_PER_MINUTE', 5),
    'per_phone_per_day'   => (int) env('WEBLEAD_PER_PHONE_PER_DAY', 3),
    'per_form_per_hour'   => (int) env('WEBLEAD_PER_FORM_PER_HOUR', 300),

    // Content filter: more links than this in the message = spam
    'max_links' => (int) env('WEBLEAD_MAX_LINKS', 1),

    // Cloudflare Turnstile. Leave the secret empty to switch the check off.
    'turnstile_secret' => env('TURNSTILE_SECRET'),
    // Site key for the hosted popup form (/f/{key}); needed only when the secret is set
    'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),

    // Website appointment booking (POST /api/v1/appointments/hold and /book), signed like the lead API.
    // Limits are per visitor (client_ip sent by the website server) and per phone.
    'booking' => [
        'holds_per_ip_per_10min'  => (int) env('BOOKING_HOLDS_PER_IP_PER_10MIN', 6),
        'active_holds_per_ip'     => (int) env('BOOKING_ACTIVE_HOLDS_PER_IP', 2),
        'hold_minutes'            => (int) env('BOOKING_HOLD_MINUTES', 10),
        'bookings_per_ip_per_hour' => (int) env('BOOKING_PER_IP_PER_HOUR', 4),
        'bookings_per_phone_per_day' => (int) env('BOOKING_PER_PHONE_PER_DAY', 2),
        'per_form_per_hour'       => (int) env('BOOKING_PER_FORM_PER_HOUR', 200),
    ],

    // OTP verification of the phone before a lead is created. Off at launch (reserved switch).
    'otp_required' => (bool) env('WEBLEAD_OTP_REQUIRED', false),
];
