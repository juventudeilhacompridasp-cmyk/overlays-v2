<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\Auth\Totp;
use App\Support\Auth\TwoFactor;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactor' => $this->twoFactor($request),
        ]);
    }

    /**
     * Estado da autenticação em dois fatores: ativa, em ativação (segredo e QR só enquanto não confirmado) e, logo depois de
     * ativar ou renovar, os códigos de recuperação (mostrados uma única vez, via flash).
     *
     * @return array<string, mixed>
     */
    private function twoFactor(Request $request): array
    {
        $user = $request->user();
        $pending = $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled();

        return [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'secret' => $pending ? $user->two_factor_secret : null,
            'otpauth' => $pending ? Totp::uri(config('app.name'), $user->email, (string) $user->two_factor_secret) : null,
            'recovery_codes_left' => TwoFactor::recoveryCodesLeft($user),
            'recovery_codes' => session('recovery_codes'),
            'required_for_admins' => (bool) config('platform.two_factor.required_for_admins'),
        ];
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
