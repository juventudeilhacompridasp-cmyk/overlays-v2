<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorChanged;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use App\Support\Auth\TwoFactorNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contas com autenticação em dois fatores, para o Super Admin reiniciar quem perdeu o aparelho e os códigos de recuperação.
 * Mostra só nome, e-mail e situação do 2FA (nunca segredo, códigos ou senha). Quem reinicia fica no log e a pessoa é avisada por e-mail.
 */
class PlatformAccountController extends Controller
{
    public function index(): Response
    {
        $users = User::query()->whereNotNull('two_factor_confirmed_at')->orderBy('name')->limit(500)->get();

        return Inertia::render('Platform/Accounts/Index', [
            'accounts' => $users->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'enabled_at' => $user->two_factor_confirmed_at?->copy()->utc()->toIso8601String(),
                'recovery_left' => TwoFactor::recoveryCodesLeft($user),
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ])->all(),
        ]);
    }

    public function reset(Request $request, User $user): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if (! $user->hasTwoFactorEnabled()) {
            return back()->with('error', 'Esta conta não tem dois fatores ativos.');
        }

        TwoFactor::disable($user);
        Log::notice('Dois fatores reiniciados por um administrador da plataforma', ['admin' => $request->user()?->getKey(), 'user' => $user->getKey()]);
        TwoFactorNotice::send($user, TwoFactorChanged::RESET);

        return back()->with('success', 'Dois fatores reiniciados. A pessoa foi avisada por e-mail e pode ativar de novo em Meu perfil.');
    }
}
