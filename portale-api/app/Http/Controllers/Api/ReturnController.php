<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrdineB2B;
use App\Models\Sostituzione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /** POST /api/returns */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ordine_originale_id' => ['required', 'uuid', 'exists:ordini_b2b,id'],
            'motivazione' => ['nullable', 'string', 'max:1000'],
            'righe' => ['required', 'array', 'min:1'],
            'righe.*.variante_resa_id' => ['required', 'uuid', 'exists:variante_prodotti,id'],
            'righe.*.variante_richiesta_id' => ['nullable', 'uuid', 'exists:variante_prodotti,id'],
            'righe.*.quantita' => ['required', 'integer', 'min:1'],
        ]);

        $ordine = OrdineB2B::findOrFail($data['ordine_originale_id']);
        abort_unless($ordine->agente_id === $request->user()->id, 403);

        $sostituzione = DB::transaction(function () use ($data, $ordine, $request) {
            $s = Sostituzione::create([
                'ordine_originale_id' => $ordine->id,
                'agente_id' => $request->user()->id,
                'cliente_id' => $ordine->cliente_id,
                'stato' => 'richiesta',
                'motivazione' => $data['motivazione'] ?? null,
            ]);

            $s->righe()->createMany($data['righe']);

            return $s;
        });

        return response()->json([
            'message' => 'Richiesta di sostituzione inviata con successo.',
            'data' => ['id' => $sostituzione->id, 'numero' => $sostituzione->numero],
        ], 201);
    }
}
