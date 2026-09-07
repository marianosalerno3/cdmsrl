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
        // API SPA agenti — Sanctum risolve il tokenable (App\Models\Agente)
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'agenti',
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
    ],

    'password_timeout' => 10800,
];
