<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Segundo fator do login: depois da senha certa, pede o código do aplicativo (ou um código de recuperação) antes de abrir a
 * sessão. O estado intermediário é só o id de quem passou na senha, na sessão, por 10 minutos.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor';

    private const MINUTES = 10;

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->pending($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('login')->withErrors(['email' => 'A verificação expirou. Entre de novo.']);
        }

        $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:30', 'required_without:code'],
        ]);

        $key = 'two-factor|'.$pending['id'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Muitas tentativas. Aguarde um minuto e tente de novo.']);
        }

        $user = User::query()->find($pending['id']);
        $usingRecovery = $request->filled('recovery_code');

        if ($user === null || ! ($usingRecovery
            ? TwoFactor::useRecoveryCode($user, (string) $request->input('recovery_code'))
            : TwoFactor::verifyCode($user, (string) $request->input('code')))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([$usingRecovery ? 'recovery_code' : 'code' => 'Código inválido.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($pending['throttle']);
        $request->session()->forget(self::SESSION_KEY);

        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** @return array{id: int|string, remember: bool, throttle: string, at: int}|null */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['id'], $pending['at']) || now()->getTimestamp() - (int) $pending['at'] > self::MINUTES * 60) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return [
            'id' => $pending['id'],
            'remember' => (bool) ($pending['remember'] ?? false),
            'throttle' => (string) ($pending['throttle'] ?? ''),
            'at' => (int) $pending['at'],
        ];
    }
}
