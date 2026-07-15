<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cross-origin requests from the Ionic Vue receiver app
    | (PWA / Capacitor webview) to the cloudPusher API.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge(
        // Deployed PWA frontend.
        ['https://push-ncloud.on-forge.com'],
        // Local dev server + native Capacitor webview origins.
        // iOS webview reports `capacitor://localhost`; Android (androidScheme: 'https') reports `https://localhost`.
        ['http://localhost:5173', 'http://localhost', 'https://localhost', 'capacitor://localhost', 'ionic://localhost'],
        // Additional origin(s) via env, comma-separated,
        // e.g. CORS_ALLOWED_ORIGINS=https://another-domain.example.com
        array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
