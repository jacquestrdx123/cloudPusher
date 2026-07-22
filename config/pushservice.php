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

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Per-minute limits for API and webhook endpoints, scoped by company.
    |
    */

    'rate_limit' => (int) env('PUSH_RATE_LIMIT', 120),

    'webhook_rate_limit' => (int) env('PUSH_WEBHOOK_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Finalization
    |--------------------------------------------------------------------------
    |
    | Seconds to wait after fan-out before aggregating delivery status.
    |
    */

    'finalize_delay_seconds' => (int) env('PUSH_FINALIZE_DELAY_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Notification Sound
    |--------------------------------------------------------------------------
    |
    | Default sound played on iOS (APNs) and Android (FCM) when a push arrives.
    | Use "default" for the system notification sound on each platform.
    | Per-push `sound` overrides this when set.
    |
    */

    'notification_sound' => env('PUSH_NOTIFICATION_SOUND', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Rich Push Defaults
    |--------------------------------------------------------------------------
    |
    | APNs category enables action buttons / the Notification Service Extension
    | path. Android channel id must match the channel the receiver app creates
    | (currently rich_messages_v1). Per-push values override these defaults.
    |
    */

    'notification_category' => env('PUSH_NOTIFICATION_CATEGORY', 'RICH_MESSAGE'),

    'android_channel_id' => env('PUSH_ANDROID_CHANNEL_ID', 'rich_messages_v1'),

    'android_notification_sound' => env('PUSH_ANDROID_NOTIFICATION_SOUND', 'notification'),

    /*
    |--------------------------------------------------------------------------
    | Mobile Login
    |--------------------------------------------------------------------------
    |
    | End-user authentication for the Ionic receiver app (phone + password).
    |
    */

    'auth' => [
        'otp_rate_limit' => (int) env('PUSH_OTP_RATE_LIMIT', 5),
        'token_ttl_days' => (int) env('PUSH_USER_TOKEN_TTL_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory Sync / Provisioning
    |--------------------------------------------------------------------------
    |
    | Machine-to-machine provisioning key used by an upstream system to create
    | companies and sync their users and groups. Leave the key empty to disable
    | the provisioning endpoint entirely. The key also authenticates the sync
    | endpoint for any company (in addition to that company's own HMAC secret).
    |
    */

    'sync' => [
        'provisioning_key' => env('PUSH_PROVISIONING_KEY'),
    ],

];
