<?php

namespace App\Modules\Matches\Scheduling;

/**
 * Conflitos de agenda (código puro): mesmo estádio em horários que se sobrepõem e a mesma equipe jogando duas vezes sem
 * tempo de descanso. Só avisa; quem agenda decide. Os tempos são em minutos.
 */
final class GameConflicts
{
    /** Duração considerada de um jogo no estádio (jogos mais próximos que isso no mesmo local conflitam). */
    public const VENUE_MINUTES = 120;

    /** Descanso mínimo entre dois jogos da mesma equipe. */
    public const TEAM_REST_MINUTES = 180;

    /**
     * @param  list<array{id: string, at: int, venue: ?string, teams: list<string>, label: string}>  $games  `at` = momento em segundos (hora marcada no local)
     * @param  list<string>|null  $focus  ids para os quais devolver os conflitos (padrão: todos)
     * @return array<string, list<array{kind: 'venue'|'team', with: string, label: string}>>
     */
    public static function find(array $games, ?array $focus = null): array
    {
        usort($games, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        $found = [];
        $window = max(self::VENUE_MINUTES, self::TEAM_REST_MINUTES) * 60;
        $closerThan = fn (int $seconds, int $minutes): bool => $seconds < $minutes * 60;

        foreach ($games as $index => $game) {
            for ($next = $index + 1; $next < count($games); $next++) {
                $other = $games[$next];

                if ($window <= $other['at'] - $game['at']) {
                    break;
                }

                $gap = $other['at'] - $game['at'];

                if ($game['venue'] !== null && $game['venue'] === $other['venue'] && $closerThan($gap, self::VENUE_MINUTES)) {
                    $found[$game['id']][] = ['kind' => 'venue', 'with' => $other['id'], 'label' => $other['label']];
                    $found[$other['id']][] = ['kind' => 'venue', 'with' => $game['id'], 'label' => $game['label']];
                }

                if (array_intersect($game['teams'], $other['teams']) !== [] && $closerThan($gap, self::TEAM_REST_MINUTES)) {
                    $found[$game['id']][] = ['kind' => 'team', 'with' => $other['id'], 'label' => $other['label']];
                    $found[$other['id']][] = ['kind' => 'team', 'with' => $game['id'], 'label' => $game['label']];
                }
            }
        }

        return $focus === null ? $found : array_intersect_key($found, array_flip($focus));
    }
}
