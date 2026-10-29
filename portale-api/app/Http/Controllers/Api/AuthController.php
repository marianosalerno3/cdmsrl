<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\RichiestaStato;
use App\Models\Agente;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Stesso form di accesso per agenti e clienti B2B: prima gli agenti, poi i clienti.
        $agente = Agente::where('email', $data['email'])->first();

        if ($agente && $agente->password && Hash::check($data['password'], $agente->password)) {
            if (! $agente->attivo) {
                throw ValidationException::withMessages([
                    'email' => 'Account agente non attivo.',
                ]);
            }

            return $this->issueToken($agente);
        }

        // Più clienti possono condividere la stessa email (anagrafica ERP): vale quello con la password giusta.
        $cliente = Cliente::where('email', $data['email'])
            ->whereNotNull('password')
            ->get()
            ->first(fn (Cliente $c) => Hash::check($data['password'], $c->password));

        if (! $cliente) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $cliente->puoAccedere()) {
            throw ValidationException::withMessages([
                'email' => match (true) {
                    $cliente->stato_richiesta === RichiestaStato::InAttesa => 'La tua registrazione è in attesa di approvazione. Ti contatteremo appena verificata.',
                    $cliente->stato_richiesta === RichiestaStato::Rifiutato => 'La tua registrazione non è stata approvata. Contatta il backoffice.',
                    default => 'Account non attivo. Contatta il backoffice.',
                },
            ]);
        }

        return $this->issueToken($cliente);
    }

    /** Un token per dispositivo/login. */
    private function issueToken(Agente|Cliente $utente): JsonResponse
    {
        return response()->json([
            'token' => $utente->createToken('spa-'.Str::random(8))->plainTextToken,
            'user' => $this->userPayload($utente),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout effettuato.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $credentials = ['email' => mb_strtolower(trim($request->input('email')))];

        // Link di reset per agenti (broker "agenti") e per clienti B2B con accesso (broker "clienti").
        // Un cliente senza password (anagrafica ERP non ancora abilitata) non può usare questa via per attivarsi:
        // l'accesso lo abilita il backoffice. Risposta sempre uguale: non riveliamo se l'email esiste.
        Password::broker('agenti')->sendResetLink($credentials);
        Password::broker('clienti')->sendResetLink($credentials);

        return response()->json([
            'message' => 'Se l\'indirizzo è registrato, riceverai un\'email con il link per reimpostare la password.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        $imposta = function (Agente|Cliente $utente, string $password) {
            $utente->forceFill(['password' => Hash::make($password)])->save();
            $utente->tokens()->delete(); // chiude le sessioni aperte con la vecchia password
        };

        // il token appartiene a uno solo dei due broker: se l'email non è di un agente proviamo con i clienti
        $status = Password::broker('agenti')->reset($credentials, $imposta);
        if ($status === Password::INVALID_USER) {
            $status = Password::broker('clienti')->reset($credentials, $imposta);
        }

        return response()->json([
            'message' => $status === Password::PASSWORD_RESET
                ? 'Password aggiornata: ora puoi accedere.'
                : 'Il link non è valido o è scaduto. Richiedine uno nuovo dalla pagina di accesso.',
        ], $status === Password::PASSWORD_RESET ? 200 : 422);
    }

    private function userPayload(Agente|Cliente $utente): array
    {
        if ($utente instanceof Cliente) {
            return [
                'id' => $utente->id,
                'ruolo' => 'cliente',
                'nome' => $utente->denominazione,
                'email' => $utente->email,
                'codice_agente' => $utente->agente?->codice_agente,
                'listino_default' => $utente->listinoEffettivo()->value,
                'commissione_perc' => null,
            ];
        }

        return [
            'id' => $utente->id,
            'ruolo' => 'agente',
            'nome' => $utente->nome,
            'email' => $utente->email,
            'codice_agente' => $utente->codice_agente,
            'listino_default' => $utente->listino_default->value,
            'commissione_perc' => (float) $utente->commissione_perc,
        ];
    }
}
