<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials and settings for your SMS
    | service provider. You can set the API key, sender ID, and other
    | necessary configurations here.
    |
    */

    'api_token' => env('SMS_API_TOKEN', null),

    'api_url' => env('SMS_API_URL', null),

    'sender_id' => env('SMS_SENDER_ID', null),

    // Shared secret for replies sent back by the gateway (POST /api/v1/sms/inbound). Empty = the endpoint is switched off.
    'inbound_secret' => env('SMS_INBOUND_SECRET', null),
];