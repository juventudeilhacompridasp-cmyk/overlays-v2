<?php

namespace App\Support\Auth;

use App\Mail\TwoFactorChanged;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Avisa a pessoa por e-mail de uma mudança nos dois fatores da conta; falha de envio só vai ao log e nunca desfaz a mudança. */
final class TwoFactorNotice
{
    public static function send(User $user, string $kind): void
    {
        try {
            Mail::to($user->email)->send(new TwoFactorChanged((string) $user->name, $kind));
        } catch (Throwable $e) {
            Log::warning('Aviso de dois fatores não enviado', ['user' => $user->getKey(), 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }
}
