<?php

namespace App\Http\Middleware;

use App\Models\Agente;
use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accesso alle API del portale: un agente attivo oppure un cliente approvato e attivo.
 * Se l'account è stato disattivato dopo l'emissione del token, il token smette di funzionare.
 */
class EnsureUtenteAttivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $ok = ($user instanceof Agente && $user->attivo)
            || ($user instanceof Cliente && $user->puoAccedere());

        if (! $ok) {
            return response()->json(['message' => 'Account non attivo.'], 403);
        }

        return $next($request);
    }
}
