<?php

namespace App\Modules\Registration\Mail;

use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Registration\Models\Registration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mails da inscrição online ao responsável: link pessoal (ao abrir o pedido) e avisos da análise
 * (devolvida, aprovada, recusada). O link é o acesso ao pedido: não o repasse a terceiros.
 */
class RegistrationMail extends Mailable
{
    /** Link pessoal montado na requisição (no worker da fila o host seria o do APP_URL, não o da organização). */
    public string $link;

    public string $organizationName;

    /** Cor da organização, lida na requisição (na fila não há organização atual). */
    public string $brandColor;

    public function __construct(public Registration $registration, public string $subjectLine, public string $headline, public ?string $note = null)
    {
        $this->link = $registration->manageUrl();
        $theme = PublicTheme::current();
        $this->organizationName = $theme['name'];
        $this->brandColor = $theme['primary'];
    }

    public static function opened(Registration $registration): self
    {
        return new self($registration, "Inscrição de {$registration->team_name}: acompanhe e complete", 'Recebemos o pedido de inscrição. Use o link abaixo para cadastrar atletas e comissão, enviar os documentos e mandar para análise.');
    }

    public static function returned(Registration $registration): self
    {
        return new self($registration, "Inscrição de {$registration->team_name}: ajustes necessários", 'A organização devolveu o pedido para ajustes. Corrija o que foi apontado e envie de novo.', $registration->review_note);
    }

    public static function approved(Registration $registration): self
    {
        if ($registration->isSupplement()) {
            return new self($registration, "Reforços de {$registration->team_name} aprovados", 'Os reforços do seu pedido foram aprovados e já fazem parte do elenco.');
        }

        return new self($registration, "Inscrição de {$registration->team_name} aprovada", 'Sua inscrição foi aprovada e a equipe já faz parte da competição.');
    }

    public static function rejected(Registration $registration): self
    {
        return new self($registration, "Inscrição de {$registration->team_name} recusada", 'Infelizmente o pedido de inscrição foi recusado.', $registration->review_note);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.registration', with: [
            'organization' => $this->organizationName,
            'link' => $this->link,
            'color' => $this->brandColor,
        ]);
    }
}
