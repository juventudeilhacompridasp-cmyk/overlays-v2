<?php

namespace App\Http\Controllers;

use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ativar, confirmar, renovar os códigos de recuperação e desativar a autenticação em dois fatores (Meu perfil). */
class TwoFactorSettingsController extends Controller
{
    /** Gera o segredo (ainda sem efeito no login). Exige a senha da conta. */
    public function begin(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if ($request->user()->hasTwoFactorEnabled()) {
            return back()->with('error', 'A autenticação em dois fatores já está ativa.');
        }

        TwoFactor::begin($request->user());

        return to_route('profile.edit');
    }

    /** Confirma com o primeiro código do aplicativo; só então passa a valer e mostra os códigos de recuperação. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $codes = TwoFactor::confirm($request->user(), (string) $request->input('code'));

        if ($codes === null) {
            return back()->withErrors(['code' => 'Código inválido. Confira o aplicativo e tente de novo.']);
        }

        return to_route('profile.edit')->with('recovery_codes', $codes)->with('success', 'Autenticação em dois fatores ativada. Guarde os códigos de recuperação.');
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if (! $request->user()->hasTwoFactorEnabled()) {
            return back()->with('error', 'Ative a autenticação em dois fatores primeiro.');
        }

        return to_route('profile.edit')->with('recovery_codes', TwoFactor::regenerateRecoveryCodes($request->user()))->with('success', 'Novos códigos gerados. Os antigos deixaram de valer.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        TwoFactor::disable($request->user());

        return to_route('profile.edit')->with('success', 'Autenticação em dois fatores desativada.');
    }
}
