<?php

return [
    // Origin della SPA agenti (redirect Stripe, link nelle email, CORS).
    'spa_url' => env('SPA_URL', 'http://localhost:5173'),

    // Default di business (override runtime da tabella app_config / pannello).
    'business' => [
        'vat_rate' => (float) env('BIZ_VAT_RATE', 22),
        'shipping_cost' => (float) env('BIZ_SHIPPING_COST', 15),
        'min_order_amount' => (float) env('BIZ_MIN_ORDER_AMOUNT', 0),
        'scheduled_order_days' => (int) env('BIZ_SCHEDULED_ORDER_DAYS', 30),
    ],

    // Path del pannello admin Filament.
    'admin_path' => env('FILAMENT_PATH', 'access'),

    // Indirizzo backoffice CDM: riceve la notifica a ogni nuovo ordine dal portale
    // (il commerciale lo prende in carico e lo carica manualmente su WinMino).
    // Più indirizzi separati da virgola.
    'backoffice_email' => env('BACKOFFICE_EMAIL', ''),
];
