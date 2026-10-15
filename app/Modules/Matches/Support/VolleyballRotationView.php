<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Actions\ProjectGameScore;
use App\Modules\Matches\Models\Game;
use App\Modules\Rules\Scoring\SetMatch;
use App\Modules\Rules\Scoring\VolleyballRotation;

/** Calcula o rodízio sem expor escalação ou dados pessoais. */
final class VolleyballRotationView
{
    /** @return array{set: int|null, configured: bool, server: 'home'|'away'|null, home_position: int, away_position: int, servers: array<string, string>}|null */
    public static function for(Game $game): ?array
    {
        if ($game->sport()->profile()->scoreModel !== 'sets') {
            return null;
        }

        $points = ProjectGameScore::pointSides($game);
        $match = new SetMatch($game->division->ruleSet());
        $set = $match->state($points)['current'];
        if ($set === null) {
            return null;
        }

        $setPoints = [];
        $current = 1;
        foreach ($points as $point) {
            $setPoints[$current][] = $point;
            $state = $match->state($setPoints[$current]);
            if ($state['finished'] === true) {
                $current++;
                $setPoints[$current] = [];
            }
        }

        $servers = (array) ($game->volleyball_rotation['servers'] ?? []);
        $initialServer = array_key_exists((string) $set, $servers) ? $servers[(string) $set] : null;
        $state = VolleyballRotation::state($setPoints[$set] ?? [], $initialServer);

        return ['set' => $set, ...$state, 'servers' => $servers];
    }
}
