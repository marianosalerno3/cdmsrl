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
        // pacchetti creati da CDM per linea, nell'ordine scelto; solo quelli con almeno un prodotto pubblicato
        $pacchetti = \App\Models\Pacchetto::query()
            ->whereHas('prodotti', fn ($q) => $q->where('attivo', true))
            ->orderBy('ordine')->orderBy('nome')->get();

        return response()->json([
            'lines' => array_column(\App\Enums\Linea::cases(), 'value'),
            'categories' => Categoria::orderBy('ordine')->orderBy('nome')->pluck('nome'),
            'seasons' => Stagione::orderByDesc('ordine')->orderBy('codice')->pluck('codice'),
            'packages' => $pacchetti->pluck('nome')->unique()->values(),
            // il menu del catalogo mostra solo i pacchetti della linea scelta: { "CLARA G": [...], "CLASSE DI VALENTINA": [...] }
            'packages_by_line' => $pacchetti->whereNotNull('linea')
                ->groupBy(fn ($p) => $p->linea->value)
                ->map(fn ($g) => $g->pluck('nome')->values()),
            'fabrics' => \App\Models\Prodotto::query()
                ->whereNotNull('tessuto')->distinct()->pluck('tessuto'),
        ]);
    }
}
