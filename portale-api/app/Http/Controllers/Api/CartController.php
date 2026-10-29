<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\Carrello;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carrello salvato sul server (uno per cliente).
 *   GET    /api/cart?cliente_id=   → righe salvate
 *   PUT    /api/cart               → salva/aggiorna (righe vuote = elimina)
 *   DELETE /api/cart?cliente_id=   → elimina
 * Il cliente lavora solo sul proprio; l'agente solo sui clienti del suo portafoglio.
 */
class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $carrello = Carrello::where('cliente_id', $this->cliente($request)->id)->first();

        return response()->json(['data' => ['items' => $carrello?->righe ?? []]]);
    }

    public function update(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request);

        $data = $request->validate([
            'items' => ['present', 'array', 'max:300'],
            'items.*.varianteId' => ['required', 'string', 'max:64'],
            'items.*.quantita' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.prezzo' => ['required', 'numeric', 'min:0'],
            'items.*.prodottoId' => ['nullable', 'string', 'max:64'],
            'items.*.codice' => ['nullable', 'string', 'max:64'],
            'items.*.nome' => ['nullable', 'string', 'max:255'],
            'items.*.taglia' => ['nullable', 'string', 'max:32'],
            'items.*.colore' => ['nullable', 'string', 'max:64'],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.programmata' => ['nullable', 'boolean'],
        ]);

        if ($data['items'] === []) {
            Carrello::where('cliente_id', $cliente->id)->delete();

            return response()->json(['data' => ['items' => []]]);
        }

        $righe = array_map(fn (array $i) => [
            'varianteId' => $i['varianteId'],
            'prodottoId' => $i['prodottoId'] ?? null,
            'codice' => $i['codice'] ?? null,
            'nome' => $i['nome'] ?? null,
            'taglia' => $i['taglia'] ?? null,
            'colore' => $i['colore'] ?? null,
            'sku' => $i['sku'] ?? null,
            'prezzo' => (float) $i['prezzo'],
            'quantita' => (int) $i['quantita'],
            'programmata' => (bool) ($i['programmata'] ?? false),
        ], $data['items']);

        $utente = $request->user();

        Carrello::updateOrCreate(['cliente_id' => $cliente->id], [
            'agente_id' => $utente instanceof Agente ? $utente->id : null,
            'righe' => $righe,
            'totale' => round(array_sum(array_map(fn ($r) => $r['prezzo'] * $r['quantita'], $righe)), 2),
            'aggiornato_at' => now(),
            'promemoria_inviato_at' => null, // il carrello è cambiato: il promemoria riparte
        ]);

        return response()->json(['data' => ['items' => $righe]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Carrello::where('cliente_id', $this->cliente($request)->id)->delete();

        return response()->json(['message' => 'Carrello eliminato.']);
    }

    /** Il cliente per cui si lavora: sé stesso se è un cliente, un cliente del portafoglio se è un agente. */
    private function cliente(Request $request): Cliente
    {
        $utente = $request->user();
        if ($utente instanceof Cliente) {
            return $utente;
        }

        $cliente = Cliente::findOrFail($request->input('cliente_id', $request->query('cliente_id')));
        abort_unless($cliente->agente_id === $utente->id, 403, 'Cliente non nel portafoglio agente.');

        return $cliente;
    }
}
