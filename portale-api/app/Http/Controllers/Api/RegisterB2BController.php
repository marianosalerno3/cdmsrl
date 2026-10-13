<?php

namespace App\Http\Controllers\Api;

use App\Enums\RichiestaStato;
use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\Cliente;
use App\Mail\NuovaRegistrazioneB2BMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/register-b2b  (multipart/form-data)
 * Registrazione autonoma di un nuovo cliente B2B con upload documenti
 * (visura, documento identità, ecc.). Crea un Cliente in stato "in_attesa" con la password scelta:
 * non può entrare finché l'admin non lo approva dal pannello (Clienti → Approva).
 */
class RegisterB2BController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ragione_sociale' => ['required', 'string', 'max:255'],
            'partita_iva' => ['required', 'string', 'max:20'],
            'codice_fiscale' => ['nullable', 'string', 'max:20'],
            // per la fatturazione elettronica serve almeno uno tra PEC e codice destinatario SDI
            'codice_sdi' => ['required_without:pec', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{6,7}$/'],
            'pec' => ['required_without:codice_sdi', 'nullable', 'email'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'indirizzo' => ['required', 'string', 'max:255'],
            'cap' => ['required', 'string', 'max:16'],
            'citta' => ['required', 'string', 'max:120'],
            'provincia' => ['required', 'string', 'max:4'],
            'agente_codice' => ['nullable', 'string'],
            'documenti' => ['nullable', 'array', 'max:6'],
            'documenti.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ], [
            'pec.required_without' => 'Inserisci la PEC oppure il Codice SDI (almeno uno dei due).',
            'codice_sdi.required_without' => 'Inserisci il Codice SDI oppure la PEC (almeno uno dei due).',
            'codice_sdi.regex' => 'Il Codice SDI deve essere di 6 o 7 caratteri (lettere e numeri).',
            'pec.email' => 'La PEC non è un indirizzo email valido.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $piva = strtoupper(preg_replace('/\s+/', '', $data['partita_iva']));

        // l'email è il login: non può coincidere con un agente o con un altro cliente che ha già l'accesso
        if (Agente::where('email', $email)->exists() || Cliente::where('email', $email)->whereNotNull('password')->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Questa email è già registrata. Accedi oppure usa un altro indirizzo.',
            ]);
        }

        // un cliente già censito (anche da ERP) non si registra due volte: l'accesso lo abilita il backoffice
        if (Cliente::where('partita_iva', $piva)->exists()) {
            throw ValidationException::withMessages([
                'partita_iva' => 'Questa partita IVA è già presente nei nostri archivi. Contatta il backoffice per attivare l\'accesso.',
            ]);
        }

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
            'partita_iva' => $piva,
            'codice_fiscale' => $data['codice_fiscale'] ?? null,
            'codice_sdi' => isset($data['codice_sdi']) ? strtoupper($data['codice_sdi']) : null,
            'pec' => $data['pec'] ?? null,
            'email' => $email,
            'password' => $data['password'],
            'telefono' => $data['telefono'] ?? null,
            'indirizzo' => $data['indirizzo'],
            'cap' => $data['cap'],
            'citta' => $data['citta'],
            'provincia' => $data['provincia'],
            'stato_richiesta' => RichiestaStato::InAttesa,
            'attivo' => false,
            'documenti' => $paths,
        ]);

        // avvisa il backoffice: c'è una richiesta da approvare
        if ($destinatari = array_filter(array_map('trim', explode(',', (string) config('portale.backoffice_email'))))) {
            Mail::to($destinatari)->queue(new NuovaRegistrazioneB2BMail($cliente));
        }

        return response()->json([
            'message' => 'Registrazione inviata. Potrai accedere appena verificheremo i tuoi dati.',
            'data' => ['id' => $cliente->id],
        ], 201);
    }
}
