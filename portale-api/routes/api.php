<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RegisterB2BController;
use Illuminate\Support\Facades\Route;

/*
| Contratto API consumato dalla SPA agenti (Vue).
| baseURL lato client: https://api.<brand>/api   ·  Auth: Bearer personal access token (Sanctum)
*/

// -------- Pubbliche --------
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/register-b2b', [RegisterB2BController::class, 'store'])->middleware('throttle:6,1');
Route::get('/filters', [FilterController::class, 'index']);

// -------- Protette: agente attivo oppure cliente B2B approvato --------
Route::middleware(['auth:sanctum', 'utente.attivo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/app-config', [AppConfigController::class, 'show']);

    // Catalogo (il listino è quello del cliente: per il cliente loggato è il suo, per l'agente quello del cliente scelto)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{prodotto}', [ProductController::class, 'show']);

    // Clienti/ordini/stagioni dell'utente loggato (per un cliente: se stesso e i propri ordini)
    Route::get('/agent/customers', [AgentController::class, 'customers']);
    Route::get('/agent/orders', [AgentController::class, 'orders']);
    Route::get('/agent/seasons', [AgentController::class, 'seasons']);

    // Carrello salvato sul server (riprendibile da altri dispositivi, base del promemoria)
    Route::get('/cart', [CartController::class, 'show']);
    Route::put('/cart', [CartController::class, 'update']);
    Route::delete('/cart', [CartController::class, 'destroy']);

    // Ordini
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{ordine}', [OrderController::class, 'show']);
    Route::put('/orders/{ordine}', [OrderController::class, 'update']);
    Route::delete('/orders/{ordine}', [OrderController::class, 'destroy']);
    Route::get('/orders/{ordine}/pdf/v2', [OrderController::class, 'pdf']);
    Route::post('/orders/{ordine}/checkout-session', [OrderController::class, 'checkoutSession']);
    Route::post('/orders/{ordine}/verify-stripe-payment', [OrderController::class, 'verifyStripePayment']);
});

// -------- Solo agenti --------
Route::middleware(['auth:sanctum', 'agente.attivo'])->group(function () {
    Route::post('/clients', [AgentController::class, 'storeClientRequest']);
});

// Webhook Stripe (fuori auth, verificato via signature)
Route::post('/stripe/webhook', [OrderController::class, 'stripeWebhook']);
