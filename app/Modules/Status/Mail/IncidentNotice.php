<?php

namespace App\Modules\Status\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso de incidente ou manutenção aos inscritos. Só textos (escapados): na fila não há nada a reler. */
class IncidentNotice extends Mailable
{
    public function __construct(public string $title, public string $stateLabel, public string $body, public string $pageLink, public string $cancelLink, public ?string $preferencesLink = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Status] {$this->title} — {$this->stateLabel}");
    }

    public function content(): Content
    {
        $e = fn (string $value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return new Content(htmlString: '<p><strong>'.$e($this->title).'</strong> — '.$e($this->stateLabel).'</p>'
            .'<p>'.nl2br($e($this->body)).'</p>'
            .'<p><a href="'.$e($this->pageLink).'">Ver a status page</a></p>'
            .'<p style="font-size:12px;color:#666">Você recebe este aviso porque confirmou a inscrição. '.($this->preferencesLink !== null ? '<a href="'.$e($this->preferencesLink).'">Escolher o que acompanhar</a> · ' : '').'<a href="'.$e($this->cancelLink).'">Cancelar avisos</a>.</p>');
    }
}
