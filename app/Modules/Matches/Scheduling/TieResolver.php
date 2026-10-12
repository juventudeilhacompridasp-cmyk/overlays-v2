<?php

namespace App\Modules\Matches\Scheduling;

/**
 * Vencedor de um confronto de mata-mata em um ou dois jogos: maior soma de gols; se empatar, decide a
 * disputa de pênaltis do último jogo. Sem critério de gol fora de casa (não é regra padrão em campeonatos amadores).
 */
final class TieResolver
{
    /**
     * @param  list<array{home: string, away: string, home_score: int, away_score: int, home_penalties: ?int, away_penalties: ?int}>  $legs  jogos já decididos, em ordem
     * @return string|null Equipe vencedora, ou null se o confronto ainda não se resolve.
     */
    public static function winner(array $legs, int $expectedLegs): ?string
    {
        if ($legs === [] || count($legs) < $expectedLegs) {
            return null;
        }

        $goals = [];

        foreach ($legs as $leg) {
            $goals[$leg['home']] = ($goals[$leg['home']] ?? 0) + $leg['home_score'];
            $goals[$leg['away']] = ($goals[$leg['away']] ?? 0) + $leg['away_score'];
        }

        $teams = array_keys($goals);

        if (count($teams) !== 2) {
            return null;
        }

        if ($goals[$teams[0]] !== $goals[$teams[1]]) {
            return $goals[$teams[0]] > $goals[$teams[1]] ? $teams[0] : $teams[1];
        }

        $last = $legs[count($legs) - 1];

        if ($last['home_penalties'] === null || $last['away_penalties'] === null || $last['home_penalties'] === $last['away_penalties']) {
            return null;
        }

        return $last['home_penalties'] > $last['away_penalties'] ? $last['home'] : $last['away'];
    }
}
