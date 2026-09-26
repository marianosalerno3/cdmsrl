<?php

use App\Http\Middleware\EnsureAgenteAttivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API stateless con Bearer token (Sanctum). Nessun EnsureFrontendRequestsAreStateful:
        // la SPA usa personal access token in localStorage, non i cookie.
        // In produzione l'app sta dietro Caddy (TLS termina li'): fidati di X-Forwarded-*
        // cosi' url()/asset()/redirect generano https e non http (mixed content nel pannello).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'agente.attivo' => EnsureAgenteAttivo::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
