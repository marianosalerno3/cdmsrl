<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agente;
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

        $agente = Agente::where('email', $data['email'])->first();

        if (! $agente || ! $agente->password || ! Hash::check($data['password'], $agente->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $agente->attivo) {
            throw ValidationException::withMessages([
                'email' => 'Account agente non attivo.',
            ]);
        }

        // Un token per dispositivo/login.
        $token = $agente->createToken('spa-'.Str::random(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($agente),
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

    private function userPayload(Agente $agente): array
    {
        return [
            'id' => $agente->id,
            'nome' => $agente->nome,
            'email' => $agente->email,
            'codice_agente' => $agente->codice_agente,
            'listino_default' => $agente->listino_default->value,
            'commissione_perc' => (float) $agente->commissione_perc,
        ];
    }
}
