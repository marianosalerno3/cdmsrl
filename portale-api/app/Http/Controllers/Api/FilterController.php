<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Stagione;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/filters — pubblico. Replica la risposta dell'originale:
 * { categories: [...], seasons: [...], packages: [...], fabrics: [...] }
 */
class FilterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => Categoria::orderBy('ordine')->orderBy('nome')->pluck('nome'),
            'seasons' => Stagione::orderByDesc('ordine')->orderBy('codice')->pluck('codice'),
            'packages' => \App\Models\Prodotto::query()
                ->whereNotNull('pacchetto')->distinct()->pluck('pacchetto'),
            'fabrics' => \App\Models\Prodotto::query()
                ->whereNotNull('tessuto')->distinct()->pluck('tessuto'),
        ]);
    }
}
