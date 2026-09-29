<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RegisterB2BController;
use App\Http\Controllers\Api\ReturnController;
use Illuminate\Support\Facades\Route;

/*
| Contratto API consumato dalla SPA agenti (Vue).
| baseURL lato client: https://api.<brand>/api   ·  Auth: Bearer personal access token (Sanctum)
*/

// -------- Pubbliche --------
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/register-b2b', [RegisterB2BController::class, 'store']);
Route::get('/filters', [FilterController::class, 'index']);

// -------- Protette (agente autenticato) --------
Route::middleware(['auth:sanctum', 'agente.attivo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/app-config', [AppConfigController::class, 'show']);

    // Catalogo
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{prodotto}', [ProductController::class, 'show']);

    // Dati dell'agente
    Route::get('/agent/customers', [AgentController::class, 'customers']);
    Route::get('/agent/orders', [AgentController::class, 'orders']);
    Route::get('/agent/seasons', [AgentController::class, 'seasons']);
    Route::get('/agent/returns', [AgentController::class, 'returns']);
    Route::post('/clients', [AgentController::class, 'storeClientRequest']);

    // Ordini
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{ordine}', [OrderController::class, 'show']);
    Route::put('/orders/{ordine}', [OrderController::class, 'update']);
    Route::delete('/orders/{ordine}', [OrderController::class, 'destroy']);
    Route::get('/orders/{ordine}/pdf/v2', [OrderController::class, 'pdf']);
    Route::post('/orders/{ordine}/checkout-session', [OrderController::class, 'checkoutSession']);
    Route::post('/orders/{ordine}/verify-stripe-payment', [OrderController::class, 'verifyStripePayment']);

    // Sostituzioni / resi
    Route::post('/returns', [ReturnController::class, 'store']);
});

// Webhook Stripe (fuori auth, verificato via signature)
Route::post('/stripe/webhook', [OrderController::class, 'stripeWebhook']);
