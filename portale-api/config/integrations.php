<?php

/*
| Valori di fallback per le integrazioni. In produzione le credenziali
| sono gestite dal pannello (pagina "Configurazioni Sistema") e salvate
| cifrate via spatie/laravel-settings (App\Settings\IntegrationSettings).
*/

return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'shopify' => [
        'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),
        'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
        'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),
    ],

    'erp' => [
        // 'null' | 'winmino' | ... (driver del gestionale del cliente)
        'driver' => env('ERP_DRIVER', 'null'),
        'base_url' => env('ERP_BASE_URL'),
        'api_key' => env('ERP_API_KEY'),
    ],
];
