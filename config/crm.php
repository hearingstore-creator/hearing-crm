<?php

/** CRM automation settings (override in .env). */
return [
    // Upper limit of automated SMS per day across ALL SMS Center automations (welcome, booking, ticket, reminders, expiry…)
    'automation_sms_daily_cap' => (int) env('CRM_AUTOMATION_SMS_DAILY_CAP', 300),

    // Verify the SMS gateway's SSL certificate. Set SMS_VERIFY_SSL=false only where PHP has no CA bundle.
    'sms_verify_ssl' => (bool) env('SMS_VERIFY_SSL', true),
];
