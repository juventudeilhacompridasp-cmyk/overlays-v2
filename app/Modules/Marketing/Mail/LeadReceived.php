<?php

namespace App\Modules\Marketing\Mail;

use App\Modules\Marketing\Models\Lead;
use App\Modules\Marketing\Support\LandingContent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso à equipe da plataforma de um novo contato da landing page. Só textos (escapados), sem modelos nem links de organização. */
class LeadReceived extends Mailable
{
    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Novo contato pela landing page: '.$this->lead->name, replyTo: [$this->lead->email]);
    }

    public function content(): Content
    {
        $e = fn (?string $value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $interest = LandingContent::INTERESTS[$this->lead->interest ?? ''] ?? '—';

        $html = '<p><strong>'.$e($this->lead->name).'</strong> ('.$e($this->lead->email).') enviou uma mensagem pela landing page.</p>'
            .'<p>Organização: '.$e($this->lead->organization_name ?: '—').'<br>Assunto: '.$e($interest).'</p>'
            .'<p>'.nl2br($e($this->lead->message)).'</p>';

        return new Content(htmlString: $html);
    }
}
