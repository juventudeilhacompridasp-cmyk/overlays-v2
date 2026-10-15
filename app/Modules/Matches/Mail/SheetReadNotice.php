<?php

namespace App\Modules\Matches\Mail;

use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de que a leitura de uma súmula enviada terminou (ou falhou). Só textos: na fila não há organização atual. Nunca leva o
 * conteúdo lido (nomes, lances), só o jogo e o endereço da revisão, que exige login no painel.
 */
class SheetReadNotice extends Mailable
{
    public string $brandColor;

    public string $organizationName;

    public function __construct(
        public string $recipientName,
        public string $subjectLine,
        public string $headline,
        public string $detail,
        public string $link,
        public string $buttonLabel,
    ) {
        $theme = PublicTheme::current();
        $this->brandColor = $theme['primary'];
        $this->organizationName = $theme['name'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sheet-read-notice', with: [
            'name' => $this->recipientName,
            'headline' => $this->headline,
            'detail' => $this->detail,
            'link' => $this->link,
            'label' => $this->buttonLabel,
            'organization' => $this->organizationName,
            'color' => $this->brandColor,
        ]);
    }
}
