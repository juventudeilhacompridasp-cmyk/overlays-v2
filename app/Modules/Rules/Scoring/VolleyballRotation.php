<?php

namespace App\Modules\Rules\Scoring;

/** Regra pura do rodízio de vôlei, sem acesso a banco ou ao framework. */
final class VolleyballRotation
{
    /**
     * @param  list<'home'|'away'>  $points
     * @return array{configured: bool, server: 'home'|'away'|null, home_position: int, away_position: int}
     */
    public static function state(array $points, ?string $initialServer): array
    {
        if ($initialServer !== 'home' && $initialServer !== 'away') {
            return ['configured' => false, 'server' => null, 'home_position' => 1, 'away_position' => 1];
        }

        $server = $initialServer;
        $positions = ['home' => 1, 'away' => 1];

        foreach ($points as $winner) {
            if ($winner !== $server) {
                $positions[$winner] = $positions[$winner] === 6 ? 1 : $positions[$winner] + 1;
                $server = $winner;
            }
        }

        return ['configured' => true, 'server' => $server, 'home_position' => $positions['home'], 'away_position' => $positions['away']];
    }
}
