<?php

namespace App\Modules\Status\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Pedido de confirmação da inscrição nos avisos da status page. Só textos e o link (sem modelos). */
class SubscriptionConfirm extends Mailable
{
    public function __construct(public string $link, public string $cancelLink) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme os avisos de status de '.config('app.name'));
    }

    public function content(): Content
    {
        $e = fn (string $value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return new Content(htmlString: '<p>Recebemos um pedido para avisar este e-mail sobre incidentes e manutenções de <strong>'.$e((string) config('app.name')).'</strong>.</p>'
            .'<p><a href="'.$e($this->link).'">Confirmar inscrição</a></p>'
            .'<p>Se não foi você, ignore esta mensagem: sem confirmação nada será enviado, e o pedido expira em 7 dias. Para nunca receber: <a href="'.$e($this->cancelLink).'">cancelar</a>.</p>');
    }
}
