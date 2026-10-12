<?php

namespace App\Modules\Matches\Scheduling;

use InvalidArgumentException;

/**
 * Chaveamento de mata-mata. Entra a lista de equipes na ordem de cabeça de chave (1ª = melhor) e sai a
 * lista de confrontos de todas as rodadas. O número de equipes não precisa ser potência de 2: as melhores
 * cabeças de chave folgam na 1ª rodada (bye) e já aparecem na 2ª. O cruzamento é o clássico (1º x último,
 * 2º x penúltimo...), de modo que as duas melhores só se enfrentam na final.
 */
final class KnockoutBracket
{
    /**
     * @param  list<string>  $entries  ordem de cabeça de chave
     * @return list<KnockoutTie> ordenados por rodada e posição
     */
    public static function build(array $entries): array
    {
        $count = count($entries);

        if ($count < 2) {
            throw new InvalidArgumentException('O mata-mata precisa de pelo menos duas equipes.');
        }

        $size = 1;
        while ($size < $count) {
            $size *= 2;
        }

        $order = self::seedOrder($size);
        $seed = fn (int $number): ?string => $number <= $count ? $entries[$number - 1] : null;

        $rounds = (int) log($size, 2);
        /** @var array<int, array<int, array{a: ?string, b: ?string, bye: bool}>> $slots */
        $slots = [];

        for ($position = 0; $position < $size / 2; $position++) {
            $a = $seed($order[$position * 2]);
            $b = $seed($order[$position * 2 + 1]);
            $slots[1][$position] = ['a' => $a, 'b' => $b, 'bye' => $a === null || $b === null];
        }

        for ($round = 2; $round <= $rounds; $round++) {
            for ($position = 0; $position < $size / (2 ** $round); $position++) {
                $slots[$round][$position] = ['a' => null, 'b' => null, 'bye' => false];
            }
        }

        // Quem folgou na 1ª rodada já ocupa seu lugar na 2ª.
        foreach ($slots[1] as $position => $tie) {
            if ($tie['bye']) {
                $slots[2][intdiv($position, 2)][$position % 2 === 0 ? 'a' : 'b'] = $tie['a'] ?? $tie['b'];
            }
        }

        $ties = [];

        foreach ($slots as $round => $roundTies) {
            foreach ($roundTies as $position => $tie) {
                $final = $round === $rounds;

                $ties[] = new KnockoutTie(
                    $round,
                    $position,
                    $tie['a'],
                    $tie['b'],
                    $tie['bye'],
                    $final ? null : intdiv($position, 2),
                    $final ? null : ($position % 2 === 0 ? 'a' : 'b'),
                );
            }
        }

        return $ties;
    }

    /** Quantidade de rodadas para o número de equipes. */
    public static function rounds(int $teams): int
    {
        $size = 1;
        $rounds = 0;

        while ($size < $teams) {
            $size *= 2;
            $rounds++;
        }

        return max(1, $rounds);
    }

    /** Nome da rodada (Final, Semifinal, Quartas...) conforme quantas rodadas faltam. */
    public static function roundLabel(int $totalRounds, int $round): string
    {
        return match ($totalRounds - $round) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Quartas de final',
            3 => 'Oitavas de final',
            default => (2 ** ($totalRounds - $round)).' avos de final',
        };
    }

    /**
     * Ordem das cabeças de chave nas vagas do chaveamento (pares consecutivos se enfrentam): 8 → 1,8,4,5,2,7,3,6.
     *
     * @return list<int>
     */
    private static function seedOrder(int $size): array
    {
        $order = [1];

        while (count($order) < $size) {
            $next = [];
            $total = count($order) * 2;

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $total + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }
}
