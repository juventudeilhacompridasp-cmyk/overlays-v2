<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;

/**
 * Pedidos de tempo de uma equipe: contados por grupo de períodos (sets, metades ou prorrogações), só os eventos
 * válidos. O agrupamento e o limite vêm do perfil do esporte.
 */
final class TeamTimeouts
{
    public static function used(Game $game, string $entryId, int $period): int
    {
        $profile = $game->sport()->profile();
        $group = $profile->timeoutGroup($period);

        return $game->events()->effective()
            ->where('type', GameEventType::Timeout)
            ->where('entry_id', $entryId)
            ->pluck('period')
            ->filter(fn ($eventPeriod) => $profile->timeoutGroup((int) $eventPeriod) === $group)
            ->count();
    }
}
