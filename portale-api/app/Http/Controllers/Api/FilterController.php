<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Stagione;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/filters — pubblico. Replica la risposta dell'originale:
 * { lines: [...], categories: [...], seasons: [...], packages: [...], fabrics: [...] }
 */
class FilterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'lines' => array_column(\App\Enums\Linea::cases(), 'value'),
            'categories' => Categoria::orderBy('ordine')->orderBy('nome')->pluck('nome'),
            'seasons' => Stagione::orderByDesc('ordine')->orderBy('codice')->pluck('codice'),
            // pacchetti creati da CDM nel pannello, nell'ordine scelto; solo quelli con almeno un prodotto pubblicato
            'packages' => \App\Models\Pacchetto::query()
                ->whereHas('prodotti', fn ($q) => $q->where('attivo', true))
                ->orderBy('ordine')->orderBy('nome')->pluck('nome'),
            'fabrics' => \App\Models\Prodotto::query()
                ->whereNotNull('tessuto')->distinct()->pluck('tessuto'),
        ]);
    }
}
