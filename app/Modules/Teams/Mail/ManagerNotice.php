<?php

namespace App\Modules\Teams\Mail;

use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso ao gestor da equipe (lembrete de escalação, mudança de horário/local, suspensões). Só textos: na fila não há
 * organização atual para reler modelos. O link é calculado por quem dispara o aviso (ver `OrganizationUrl`).
 */
class ManagerNotice extends Mailable
{
    public string $brandColor;

    /** @param  list<string>  $lines */
    public function __construct(
        public string $recipientName,
        public string $subjectLine,
        public string $headline,
        public array $lines,
        public string $link,
        public string $organizationName,
    ) {
        $this->brandColor = PublicTheme::current()['primary'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.manager-notice', with: [
            'name' => $this->recipientName,
            'headline' => $this->headline,
            'lines' => $this->lines,
            'link' => $this->link,
            'organization' => $this->organizationName,
            'color' => $this->brandColor,
        ]);
    }
}
