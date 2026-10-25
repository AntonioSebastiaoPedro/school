<?php

namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): Response
    {
        // A sessão por cookie só existe para pedidos vindos do frontend (SANCTUM_STATEFUL_DOMAINS).
        abort_unless($request->hasSession(), 400, 'Origem não autorizada para iniciar sessão.');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt([...$credentials, 'status' => 'active'])) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        $request->session()->regenerate();
        $request->user('web')->forceFill(['last_login_at' => now()])->save();

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $memberships = DB::table('institution_user')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $institutions = Institution::query()->whereIn('id', $memberships->pluck('institution_id'))->get()->keyBy('id');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_platform_admin' => $user->is_platform_admin,
            'institutions' => $memberships->map(fn ($m) => [
                'institution_id' => $m->institution_id,
                'institution_name' => $institutions[$m->institution_id]->name ?? null,
                'role' => $m->role,
                'status' => $m->status,
            ])->values(),
        ]]);
    }

    /** RF-025. A resposta é sempre a mesma, para não revelar que e-mails existem. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($data);

        return response()->json(['message' => 'Se o e-mail existir, receberá as instruções.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => $password])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Ligação de recuperação inválida ou expirada.']);
        }

        return response()->json(['message' => 'Palavra-passe actualizada.']);
    }
}
