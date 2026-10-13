<?php

namespace App\Modules\Market\Mail;

use App\Modules\Portal\Support\PublicTheme;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso do Mercado (pedido de contato, resposta, perfil perto de expirar). Só textos: na fila não há organização atual.
 * Nunca leva contato do atleta nem dados de outra pessoa; o link é calculado por quem dispara (`route()` na requisição ou
 * `OrganizationUrl` fora dela).
 */
class MarketNotice extends Mailable
{
    public string $brandColor;

    public string $organizationName;

    /** @param  list<string>  $lines */
    public function __construct(
        public string $recipientName,
        public string $subjectLine,
        public string $headline,
        public array $lines,
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
        return new Content(view: 'mail.market-notice', with: [
            'name' => $this->recipientName,
            'headline' => $this->headline,
            'lines' => $this->lines,
            'link' => $this->link,
            'label' => $this->buttonLabel,
            'organization' => $this->organizationName,
            'color' => $this->brandColor,
        ]);
    }
}
