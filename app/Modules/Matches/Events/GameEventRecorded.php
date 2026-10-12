<?php

namespace App\Modules\Matches\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Um lançamento foi registrado na súmula (gol, cartão, substituição). Contrato entre módulos: quem precisa reagir
 * (ex.: overlays de transmissão) escuta este evento em vez de ser chamado pelas Actions de Matches.
 */
class GameEventRecorded
{
    use Dispatchable;

    public function __construct(public string $gameId, public string $eventId) {}
}
