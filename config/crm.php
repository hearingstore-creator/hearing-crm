<?php

/** CRM automation settings (override in .env). */
return [
    // Upper limit of automated SMS per day across ALL SMS Center automations (welcome, booking, ticket, reminders, expiry…)
    'automation_sms_daily_cap' => (int) env('CRM_AUTOMATION_SMS_DAILY_CAP', 300),

    // Verify the SMS gateway's SSL certificate. Set SMS_VERIFY_SSL=false only where PHP has no CA bundle.
    'sms_verify_ssl' => (bool) env('SMS_VERIFY_SSL', true),

    // SMS campaigns: above this many recipients only Super Admin may send
    'sms_campaign_admin_threshold' => (int) env('SMS_CAMPAIGN_ADMIN_THRESHOLD', 500),
    // Quiet hours (Bangladesh time): campaigns sent in this window wait until the end hour
    'sms_quiet_from_hour' => (int) env('SMS_QUIET_FROM_HOUR', 21),
    'sms_quiet_until_hour' => (int) env('SMS_QUIET_UNTIL_HOUR', 9),

    // Activity log rows older than this many months are deleted by the monthly clean-up
    'activity_log_keep_months' => (int) env('ACTIVITY_LOG_KEEP_MONTHS', 24),
];
