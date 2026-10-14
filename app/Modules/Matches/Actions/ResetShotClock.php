<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Events\GameEventRecorded;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ResetShotClock
{
    public function handle(Game $game, int $seconds, ?int $recordedBy = null, ?Carbon $at = null): GameEvent
    {
        if ($game->sport()->profile()->scoreModel !== 'points' || ! $game->sport()->profile()->pausableClock) {
            throw new InvalidArgumentException('Este esporte não usa relógio de posse.');
        }

        if (! in_array($seconds, [14, 24], true)) {
            throw new InvalidArgumentException('O relógio de posse só pode reiniciar em 14 ou 24 segundos.');
        }

        if ($game->status->isDecided() || in_array($game->status, [GameStatus::Cancelled, GameStatus::Postponed], true)) {
            throw new InvalidArgumentException('O jogo não está aberto para lançamentos.');
        }

        $clock = $game->clock()->snapshot(ChangeGamePeriod::marks($game));

        if (! in_array($clock['state'], ['running', 'paused'], true) || $clock['period'] === null) {
            throw new InvalidArgumentException('Inicie um período antes de reiniciar o relógio de posse.');
        }

        $event = GameEvent::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'type' => GameEventType::ShotClockReset,
            'period' => $clock['period'],
            'payload' => ['seconds' => $seconds],
            'recorded_by' => $recordedBy,
            'created_at' => $at ?? now(),
        ]);

        event(GameUpdated::for($game));
        GameEventRecorded::dispatch((string) $game->getKey(), (string) $event->getKey());

        return $event;
    }
}
