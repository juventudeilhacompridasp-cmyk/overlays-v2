<?php

namespace App\Modules\Teams\Mail;

use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Convite do gestor de equipe. Conta nova: link para definir a senha (vale 24 h; depois, "Esqueci a senha").
 * Conta existente: aviso com o link do painel do gestor. O link é calculado na requisição (ver `InviteTeamManager`).
 */
class TeamManagerInvitation extends Mailable
{
    public string $organizationName;

    public string $brandColor;

    /** Só textos (nada de modelos): na fila não há organização atual para reler registros com o escopo da organização. */
    public function __construct(public string $recipientName, public string $teamName, public string $link, public bool $newAccount)
    {
        $theme = PublicTheme::current();
        $this->organizationName = $theme['name'];
        $this->brandColor = $theme['primary'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Você é gestor de {$this->teamName}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.team-manager', with: [
            'name' => $this->recipientName,
            'team' => $this->teamName,
            'organization' => $this->organizationName,
            'link' => $this->link,
            'newAccount' => $this->newAccount,
            'color' => $this->brandColor,
        ]);
    }
}
