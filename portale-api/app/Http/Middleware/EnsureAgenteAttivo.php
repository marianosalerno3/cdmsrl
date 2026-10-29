<?php

namespace App\Http\Middleware;

use App\Models\Agente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgenteAttivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Agente || ! $user->attivo) {
            return response()->json(['message' => 'Account agente non attivo.'], 403);
        }

        return $next($request);
    }
}
