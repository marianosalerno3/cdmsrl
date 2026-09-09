<?php

namespace App\Http\Controllers\Api;

use App\Enums\RichiestaStato;
use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/register-b2b  (multipart/form-data)
 * Registrazione autonoma di un nuovo cliente B2B con upload documenti
 * (visura, documento identità, ecc.). Crea un Cliente in stato "in_attesa".
 */
class RegisterB2BController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ragione_sociale' => ['required', 'string', 'max:255'],
            'partita_iva' => ['required', 'string', 'max:20'],
            'codice_fiscale' => ['nullable', 'string', 'max:20'],
            'codice_sdi' => ['nullable', 'string', 'max:12'],
            'pec' => ['nullable', 'email'],
            'email' => ['required', 'email'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'indirizzo' => ['required', 'string', 'max:255'],
            'cap' => ['required', 'string', 'max:16'],
            'citta' => ['required', 'string', 'max:120'],
            'provincia' => ['required', 'string', 'max:4'],
            'agente_codice' => ['nullable', 'string'],
            'documenti' => ['nullable', 'array', 'max:6'],
            'documenti.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ]);

        $paths = [];
        foreach ($request->file('documenti', []) as $file) {
            $paths[] = $file->store('documenti-b2b', 'public');
        }

        $agente = ! empty($data['agente_codice'])
            ? Agente::where('codice_agente', $data['agente_codice'])->first()
            : null;

        $cliente = Cliente::create([
            'agente_id' => $agente?->id,
            'tipo' => 'b2b',
            'ragione_sociale' => $data['ragione_sociale'],
            'partita_iva' => $data['partita_iva'],
            'codice_fiscale' => $data['codice_fiscale'] ?? null,
            'codice_sdi' => $data['codice_sdi'] ?? null,
            'pec' => $data['pec'] ?? null,
            'email' => $data['email'],
            'telefono' => $data['telefono'] ?? null,
            'indirizzo' => $data['indirizzo'],
            'cap' => $data['cap'],
            'citta' => $data['citta'],
            'provincia' => $data['provincia'],
            'stato_richiesta' => RichiestaStato::InAttesa,
            'attivo' => false,
            'documenti' => $paths,
        ]);

        return response()->json([
            'message' => 'Registrazione inviata. Verrai contattato dopo la verifica.',
            'data' => ['id' => $cliente->id],
        ], 201);
    }
}
