<?php

namespace App\Http\Controllers\Api;

use App\Enums\RichiestaStato;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Stagione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * GET /api/agent/customers — portafoglio clienti dell'agente autenticato.
     * Per un cliente loggato l'elenco contiene soltanto lui stesso (così la SPA lo seleziona in automatico).
     */
    public function customers(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Cliente) {
            return response()->json(['data' => [$this->customerRow($user)]]);
        }

        $clienti = $user->clienti()
            ->where('attivo', true)
            ->where('stato_richiesta', RichiestaStato::Approvato)
            ->orderBy('ragione_sociale')
            ->get()
            ->map(fn (Cliente $c) => $this->customerRow($c));

        return response()->json(['data' => $clienti]);
    }

    /** GET /api/agent/orders — ordini dell'agente, oppure i propri ordini se l'utente è un cliente. */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();

        // Agente::ordini() e Cliente::ordini() hanno la stessa forma: l'utente vede solo i propri ordini.
        $ordini = $user->ordini()
            ->with('cliente')
            ->when($request->string('season')->toString(), fn ($q, $s) => $q
                ->whereHas('righe.variante.prodotto.stagione', fn ($q) => $q->where('codice', $s)))
            ->latest('data_ordine')
            ->paginate(20)
            ->withQueryString();

        return response()->json($ordini);
    }

    private function customerRow(Cliente $c): array
    {
        return [
            'id' => $c->id,
            'denominazione' => $c->denominazione,
            'partita_iva' => $c->partita_iva,
            'listino' => $c->listinoEffettivo()->value,
            'contrassegno_abilitato' => $c->contrassegno_abilitato,
            'indirizzo' => trim("{$c->indirizzo}, {$c->cap} {$c->citta} ({$c->provincia})", ', '),
        ];
    }

    /** GET /api/agent/seasons */
    public function seasons(): JsonResponse
    {
        return response()->json([
            'data' => Stagione::where('attiva', true)
                ->orderByDesc('ordine')->orderBy('codice')
                ->get(['codice', 'nome', 'programmata']),
        ]);
    }

    /** POST /api/clients — l'agente propone un nuovo cliente (stato "in attesa"). */
    public function storeClientRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ragione_sociale' => ['required', 'string', 'max:255'],
            'partita_iva' => ['required', 'string', 'max:20'],
            'codice_fiscale' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'indirizzo' => ['nullable', 'string', 'max:255'],
            'cap' => ['nullable', 'string', 'max:16'],
            'citta' => ['nullable', 'string', 'max:120'],
            'provincia' => ['nullable', 'string', 'max:4'],
        ]);

        $cliente = $request->user()->clienti()->create([
            ...$data,
            'tipo' => 'b2b',
            'stato_richiesta' => RichiestaStato::InAttesa,
            'attivo' => false,
        ]);

        return response()->json([
            'message' => 'Richiesta cliente inviata con successo!',
            'data' => ['id' => $cliente->id],
        ], 201);
    }
}
