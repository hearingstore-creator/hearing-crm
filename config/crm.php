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

    // Which dashboard /admin/dashboard opens: "legacy" (the old one, kept until the new one is accepted) or "new".
    // Both stay reachable at /admin/dashboard/legacy and /admin/dashboard/new.
    'dashboard' => env('CRM_DASHBOARD', 'legacy') === 'new' ? 'new' : 'legacy',

    // Cost saved on sale lines: 'shadow' keeps the cost posted by the form and logs differences from the purchase records;
    // 'server' saves the cost found in the purchase records (the form value is only a fallback).
    'sale_cost_mode' => env('CRM_SALE_COST_MODE', 'shadow') === 'server' ? 'server' : 'shadow',

    // Stock adjustment screens (damaged, lost, found, count correction, opening stock). Off until switched on.
    'stock_adjustment' => (bool) env('CRM_STOCK_ADJUSTMENT', false),

    // Stock transfer screens (move stock between head office and branches). Off until switched on.
    'stock_transfer' => (bool) env('CRM_STOCK_TRANSFER', false),

    // Hours a ticket may stay unanswered, by priority. The inbox shows the time left; the clock pauses while a
    // ticket is waiting on the patient (Pending / On Hold) and stops when it is Solved or Closed.
    'ticket_sla_hours' => ['Critical' => 4, 'High' => 24, 'Medium' => 72, 'Low' => 168],

    // Activity log rows older than this many months are deleted by the monthly clean-up
    'activity_log_keep_months' => (int) env('ACTIVITY_LOG_KEEP_MONTHS', 24),

    // Written by deploy/backup-offsite.sh after every nightly run; read by crm:backup-check to alert the owner
    'backup_status_file' => env('CRM_BACKUP_STATUS_FILE', '/var/backups/crm/status.json'),
    // A backup older than this many hours counts as stopped (the job runs nightly, so 30 allows for a late run)
    'backup_max_age_hours' => (int) env('CRM_BACKUP_MAX_AGE_HOURS', 30),
];
