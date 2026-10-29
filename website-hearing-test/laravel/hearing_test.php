<?php

// Website config: config/hearing_test.php
return [
    'phone'       => env('HEARING_TEST_PHONE', ''),        // +8801XXXXXXXXX
    'whatsapp'    => env('HEARING_TEST_WHATSAPP', ''),     // 8801XXXXXXXXX
    'booking_url' => env('HEARING_TEST_BOOKING_URL', ''),  // e.g. /book-appointment

    // Phase 2: send leads into the CRM (POST /api/v1/leads, HMAC signed).
    'crm_enabled'  => env('HEARING_TEST_CRM_ENABLED', false),
    'crm_url'      => env('CRM_URL', 'https://crm.zenhear.com'),
    'crm_form_key' => env('CRM_LEAD_FORM_KEY'),
    'crm_secret'   => env('CRM_LEAD_FORM_SECRET'),
];
