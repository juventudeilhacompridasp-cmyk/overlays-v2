<?php

namespace App\Modules\Matches\Scheduling;

use InvalidArgumentException;

/**
 * Tabela de pontos corridos pelo método do círculo (todos contra todos).
 *
 * Com número ímpar de equipes, uma folga por rodada. O mando de campo é distribuído para equilibrar
 * jogos em casa e fora: em cada confronto joga em casa quem tem menos jogos em casa até ali; no empate,
 * quem jogou fora por último. Em ida e volta, o returno repete as rodadas com o mando invertido
 * (todas as equipes terminam com o mesmo número de jogos em casa).
 */
final class RoundRobin
{
    /**
     * @param  array<int, string>  $teamIds  Na ordem de cabeça de chave (as chaves são ignoradas).
     * @return list<list<array{0: string, 1: string}>> Rodadas, cada uma com pares [mandante, visitante].
     */
    public static function rounds(array $teamIds, bool $doubleRound = false): array
    {
        $teamIds = array_values($teamIds);

        if (count($teamIds) !== count(array_unique($teamIds))) {
            throw new InvalidArgumentException('As equipes da tabela não podem se repetir.');
        }

        if (count($teamIds) < 2) {
            return [];
        }

        $pairsByRound = self::pairings($teamIds);

        $home = array_fill_keys($teamIds, 0);
        $lastWasHome = array_fill_keys($teamIds, null);
        $rounds = [];

        foreach ($pairsByRound as $pairs) {
            $round = [];

            foreach ($pairs as [$a, $b]) {
                $aHome = self::prefersHome($a, $b, $home, $lastWasHome);

                [$h, $v] = $aHome ? [$a, $b] : [$b, $a];
                $home[$h]++;
                $lastWasHome[$h] = true;
                $lastWasHome[$v] = false;
                $round[] = [$h, $v];
            }

            $rounds[] = $round;
        }

        if ($doubleRound) {
            foreach ($rounds as $round) {
                $rounds[] = array_map(fn (array $pair) => [$pair[1], $pair[0]], $round);
            }
        }

        return $rounds;
    }

    /**
     * Pares por rodada (sem mando), método do círculo.
     *
     * @param  list<string>  $teams
     * @return list<list<array{0: string, 1: string}>>
     */
    private static function pairings(array $teams): array
    {
        $slots = $teams;

        if (count($slots) % 2 === 1) {
            $slots[] = null; // folga
        }

        $count = count($slots);
        $rounds = [];

        for ($r = 0; $r < $count - 1; $r++) {
            $pairs = [];

            for ($i = 0; $i < $count / 2; $i++) {
                $a = $slots[$i];
                $b = $slots[$count - 1 - $i];

                if ($a !== null && $b !== null) {
                    $pairs[] = [$a, $b];
                }
            }

            $rounds[] = $pairs;

            // Mantém o primeiro fixo e gira os demais.
            $last = array_pop($slots);
            array_splice($slots, 1, 0, [$last]);
        }

        return $rounds;
    }

    /**
     * @param  array<string, int>  $home
     * @param  array<string, bool|null>  $lastWasHome
     */
    private static function prefersHome(string $a, string $b, array $home, array $lastWasHome): bool
    {
        if ($home[$a] !== $home[$b]) {
            return $home[$a] < $home[$b];
        }

        // Empate: quem jogou fora por último recebe o mando; se ambos iguais, mantém a ordem informada.
        $aAway = $lastWasHome[$a] === false;
        $bAway = $lastWasHome[$b] === false;

        if ($aAway !== $bAway) {
            return $aAway;
        }

        return true;
    }
}
