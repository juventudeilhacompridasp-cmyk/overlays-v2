<?php

namespace App\Modules\Matches\Mail;

use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class GameSignatureInvitation extends Mailable
{
    public string $organization;

    public string $color;

    public function __construct(public string $recipientName, public string $role, public string $link)
    {
        $theme = PublicTheme::current();
        $this->organization = $theme['name'];
        $this->color = $theme['primary'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirmação de súmula da partida');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.game-signature-invitation');
    }
}
