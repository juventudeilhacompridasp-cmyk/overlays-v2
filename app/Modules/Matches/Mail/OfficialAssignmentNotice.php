<?php

namespace App\Modules\Matches\Mail;

use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso a quem foi escalado para um jogo. Só textos (na fila não há organização atual): função, jogo, data e local. Árbitros e
 * mesários não têm login: o e-mail é a única via.
 */
class OfficialAssignmentNotice extends Mailable
{
    public string $brandColor;

    public string $organizationName;

    public function __construct(
        public string $recipientName,
        public string $roleLabel,
        public string $gameLabel,
        public string $competition,
        public string $when,
        public string $venue,
    ) {
        $theme = PublicTheme::current();
        $this->brandColor = $theme['primary'];
        $this->organizationName = $theme['name'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Escala: {$this->roleLabel} em {$this->gameLabel}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.official-assignment', with: [
            'name' => $this->recipientName,
            'role' => $this->roleLabel,
            'game' => $this->gameLabel,
            'competition' => $this->competition,
            'when' => $this->when,
            'venue' => $this->venue,
            'organization' => $this->organizationName,
            'color' => $this->brandColor,
        ]);
    }
}
