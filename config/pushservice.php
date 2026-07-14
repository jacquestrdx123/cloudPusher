<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery Providers
    |--------------------------------------------------------------------------
    |
    | Toggles for each outbound provider. A channel requested by a webhook is
    | only attempted when its provider is enabled here AND the recipient has a
    | matching route (device token, phone, or email). This lets the service run
    | end to end without real FCM / APNs / SMS credentials configured.
    |
    */

    'providers' => [
        'fcm' => (bool) env('PUSH_FCM_ENABLED', false),
        'apns' => (bool) env('PUSH_APNS_ENABLED', false),
        'sms' => (bool) env('PUSH_SMS_ENABLED', false),
        'mail' => (bool) env('PUSH_MAIL_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue that inbound webhook processing and outbound notifications are
    | pushed onto.
    |
    */

    'queue' => env('PUSH_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Signature Header
    |--------------------------------------------------------------------------
    |
    | HTTP header a webhook caller must send containing the HMAC-SHA256 hex
    | signature of the raw request body, prefixed with the algorithm, e.g.
    | "sha256=<hex>".
    |
    */

    'signature_header' => env('PUSH_SIGNATURE_HEADER', 'X-Signature'),

];

