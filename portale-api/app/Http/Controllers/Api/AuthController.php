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

        // I clienti B2B non hanno il link di reset: la password la reimposta il backoffice dal pannello (Clienti → Imposta accesso).
        if (! Agente::where('email', $request->input('email'))->exists()
            && Cliente::where('email', $request->input('email'))->whereNotNull('password')->exists()) {
            return response()->json([
                'message' => 'Per reimpostare la password contatta il backoffice: ti riattiveremo l\'accesso.',
            ]);
        }

        // Broker dedicato agli agenti (vedi config/auth.php -> passwords.agenti).
        $status = Password::broker('agenti')->sendResetLink($request->only('email'));

        return response()->json([
            'message' => __($status),
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $status = Password::broker('agenti')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Agente $agente, string $password) {
                $agente->forceFill(['password' => Hash::make($password)])->save();
                $agente->tokens()->delete();
            }
        );

        return response()->json([
            'message' => __($status),
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
