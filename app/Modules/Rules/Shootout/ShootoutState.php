<?php

namespace App\Modules\Rules\Shootout;

/**
 * Regras puras da disputa de pênaltis cobrança a cobrança (sem banco). Os times se alternam a partir de quem cobrou
 * primeiro; nas `rounds` primeiras cobranças de cada lado a disputa acaba assim que um lado não pode mais ser alcançado
 * (mesmo com as cobranças que faltam); depois disso é morte súbita: vale o placar quando os dois lados cobraram o mesmo
 * número de vezes e ele não está empatado.
 */
final class ShootoutState
{
    public const ROUNDS = 5;

    /** Limite de cobranças por equipe (o mesmo do total lançado à mão). */
    public const MAX_PER_SIDE = 30;

    /**
     * @param  list<array{side: string, scored: bool}>  $kicks  em ordem; `side` = `home` ou `away`
     * @return array{home: list<bool>, away: list<bool>, goals: array{home: int, away: int}, first: string|null, next: string|null, decided: bool, winner: string|null, rounds: int}
     */
    public static function from(array $kicks, int $rounds = self::ROUNDS): array
    {
        $taken = ['home' => [], 'away' => []];

        foreach ($kicks as $kick) {
            $taken[$kick['side']][] = $kick['scored'];
        }

        $goals = ['home' => count(array_filter($taken['home'])), 'away' => count(array_filter($taken['away']))];
        $count = ['home' => count($taken['home']), 'away' => count($taken['away'])];
        $first = $kicks === [] ? null : $kicks[0]['side'];
        $winner = self::winner($goals, $count, $rounds);

        return [
            'home' => $taken['home'],
            'away' => $taken['away'],
            'goals' => $goals,
            'first' => $first,
            'next' => $winner !== null || $first === null ? null : self::next($count, $first),
            'decided' => $winner !== null,
            'winner' => $winner,
            'rounds' => $rounds,
        ];
    }

    /**
     * Quem cobra agora (null enquanto ninguém cobrou: qualquer lado pode abrir a disputa).
     *
     * @param  array{home: int, away: int}  $count
     */
    private static function next(array $count, string $first): string
    {
        $other = $first === 'home' ? 'away' : 'home';

        return $count[$first] <= $count[$other] ? $first : $other;
    }

    /**
     * @param  array{home: int, away: int}  $goals
     * @param  array{home: int, away: int}  $count
     */
    private static function winner(array $goals, array $count, int $rounds): ?string
    {
        $leader = $goals['home'] === $goals['away'] ? null : ($goals['home'] > $goals['away'] ? 'home' : 'away');

        if (max($count) <= $rounds) {
            // Fase das cobranças regulares: acaba quando o outro lado não alcança nem acertando tudo o que falta.
            foreach (['home' => 'away', 'away' => 'home'] as $side => $other) {
                if ($goals[$side] > $goals[$other] + max(0, $rounds - $count[$other])) {
                    return $side;
                }
            }

            return null;
        }

        // Morte súbita: só vale com os dois lados tendo cobrado o mesmo número de vezes.
        return $count['home'] === $count['away'] ? $leader : null;
    }
}
