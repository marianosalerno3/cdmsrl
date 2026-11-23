<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        // Pannello admin Filament
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        // API SPA — Sanctum risolve il tokenable dal token: App\Models\Agente oppure App\Models\Cliente.
        // Nessun 'provider': con un provider fisso Sanctum scarterebbe i token dei clienti.
        'sanctum' => [
            'driver' => 'sanctum',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'agenti' => [
            'driver' => 'eloquent',
            'model' => App\Models\Agente::class,
        ],
        // clienti B2B con accesso (solo per il reset password, vedi App\Auth\ClientiUserProvider)
        'clienti' => [
            'driver' => 'clienti-con-accesso',
            'model' => App\Models\Cliente::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'agenti' => [
            'provider' => 'agenti',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'clienti' => [
            'provider' => 'clienti',
            'table' => 'cliente_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
