<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso de segurança à pessoa quando a autenticação em dois fatores da conta é ativada, desativada ou reiniciada. Só textos. */
class TwoFactorChanged extends Mailable
{
    public const ENABLED = 'enabled';

    public const DISABLED = 'disabled';

    public const RESET = 'reset';

    public function __construct(public string $recipientName, public string $kind) {}

    public function headline(): string
    {
        return match ($this->kind) {
            self::ENABLED => 'A autenticação em dois fatores foi ativada na sua conta.',
            self::RESET => 'Um administrador da plataforma reiniciou a autenticação em dois fatores da sua conta. Ela está desativada: entre com a senha e ative de novo em Meu perfil.',
            default => 'A autenticação em dois fatores foi desativada na sua conta.',
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            self::ENABLED => 'Dois fatores ativados na sua conta',
            self::RESET => 'Dois fatores reiniciados na sua conta',
            default => 'Dois fatores desativados na sua conta',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.two-factor-changed', with: [
            'organization' => config('app.name'),
            'color' => (string) config('platform.default_color', '#059669'),
            'name' => $this->recipientName,
            'headline' => $this->headline(),
        ]);
    }
}
