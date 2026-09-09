<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'stripe/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('SPA_URL', 'http://localhost:5173'),
        'http://localhost:5173',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // La SPA usa Bearer token (non cookie), quindi le credenziali non sono necessarie.
    'supports_credentials' => true,
];
