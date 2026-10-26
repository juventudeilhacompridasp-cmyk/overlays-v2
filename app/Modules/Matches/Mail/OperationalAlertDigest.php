<?php

namespace App\Modules\Matches\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Resumo dos jogos próximos com algo a resolver. Só textos (equipes, horários e o que falta), nunca modelos nem dados pessoais. */
class OperationalAlertDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  list<array{label: string, at: string, problems: list<string>}>  $games */
    public function __construct(public string $organization, public string $recipientName, public array $games, public string $link) {}

    public function envelope(): Envelope
    {
        $count = count($this->games);

        return new Envelope(subject: $count === 1 ? '1 jogo precisa de atenção' : "{$count} jogos precisam de atenção");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.operational-alert-digest', with: [
            'organization' => $this->organization,
            'color' => (string) config('platform.default_color', '#059669'),
            'name' => $this->recipientName,
            'games' => array_map(fn (array $game) => $game + ['when' => Carbon::parse($game['at'])->timezone(config('app.timezone'))->format('d/m H:i')], $this->games),
            'link' => $this->link,
        ]);
    }
}
