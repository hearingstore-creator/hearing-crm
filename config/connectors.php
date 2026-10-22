<?php

/**
 * Lead connectors (Leads → Lead Inbox → Connections). Each one stays switched off until its keys are set.
 */
return [
    // Meta (Facebook / Instagram Lead Ads and WhatsApp Cloud API) – one webhook for the Meta app:
    //   https://<crm>/api/v1/hooks/meta   (Verify token = META_VERIFY_TOKEN)
    'meta' => [
        'app_secret'    => env('META_APP_SECRET'),       // checks that calls really come from Meta (X-Hub-Signature-256)
        'verify_token'  => env('META_VERIFY_TOKEN'),     // any text you choose; typed into the Meta app's webhook setup
        'page_token'    => env('META_PAGE_TOKEN'),       // long-lived Page access token with leads_retrieval, to read each lead
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        // Capture form (Lead Inbox → Capture forms) whose branch, SLA and default interest these leads take
        'form_key'      => env('META_LEAD_FORM_KEY'),
        'whatsapp_form_key' => env('WHATSAPP_LEAD_FORM_KEY'),   // empty = same as form_key
        // A WhatsApp number writing again within this many hours is the same enquiry (no new inbox row)
        'whatsapp_repeat_hours' => (int) env('WHATSAPP_REPEAT_HOURS', 24),
    ],
];
