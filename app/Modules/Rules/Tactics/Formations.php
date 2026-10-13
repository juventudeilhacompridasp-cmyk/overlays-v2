<?php

namespace App\Modules\Rules\Tactics;

use InvalidArgumentException;

/**
 * Formações por esporte (ADR 0003). Futebol e futsal em linhas (goleiro + linhas de campo), basquete em cinco posições
 * e vôlei pelas seis posições da quadra (sistemas 5-1, 6-2 e 4-2, com líbero no banco).
 */
final class Formations
{
    /** @return list<Formation> */
    public static function for(string $sport): array
    {
        return match ($sport) {
            'football' => array_map(fn (string $code) => self::lines($code, 'football'), ['4-4-2', '4-3-3', '4-2-3-1', '4-1-4-1', '4-5-1', '3-5-2', '3-4-3', '5-3-2']),
            'futsal' => self::futsal(),
            'basketball' => [self::basketball()],
            'volleyball' => [self::volleyball('5-1', 'Sistema 5-1', ['Levantador', 'Oposto', 'Central', 'Ponteiro', 'Ponteiro', 'Central']),
                self::volleyball('6-2', 'Sistema 6-2', ['Levantador', 'Levantador', 'Central', 'Ponteiro', 'Ponteiro', 'Central']),
                self::volleyball('4-2', 'Sistema 4-2', ['Levantador', 'Ponteiro', 'Central', 'Levantador', 'Ponteiro', 'Central'])],
            default => throw new InvalidArgumentException("Esporte sem formações: {$sport}."),
        };
    }

    public static function find(string $sport, string $code): ?Formation
    {
        foreach (self::for($sport) as $formation) {
            if ($formation->code === $code) {
                return $formation;
            }
        }

        return null;
    }

    /** Formação inicial sugerida do esporte. */
    public static function default(string $sport): Formation
    {
        return self::for($sport)[0];
    }

    /**
     * Valida as posições dos titulares (`personId => chave da vaga`) e do banco contra uma formação: cada vaga existe e
     * só é usada uma vez; vagas de reserva (líbero) só por reservas e as demais só por titulares.
     *
     * @param  array<string, string>  $starters
     * @param  array<string, string>  $bench
     */
    public static function validate(Formation $formation, array $starters, array $bench = []): ?string
    {
        $used = [];

        foreach ([[$starters, false], [$bench, true]] as [$positions, $onBench]) {
            foreach ($positions as $key) {
                $slot = $formation->slot($key);

                if ($slot === null) {
                    return 'Posição inexistente nesta formação.';
                }

                if ($slot->bench !== $onBench) {
                    return $onBench ? 'Esta posição é só de titulares.' : "A posição “{$slot->label}” é de um reserva.";
                }

                if (isset($used[$key])) {
                    return "A posição “{$slot->label}” já está ocupada.";
                }

                $used[$key] = true;
            }
        }

        return null;
    }

    /**
     * Futebol: "4-3-3" = goleiro + linhas de defesa, meio e ataque (4 linhas: volantes e meias separados).
     */
    private static function lines(string $code, string $sport): Formation
    {
        $lines = array_map('intval', explode('-', $code));
        $count = count($lines);
        $slots = [new FormationSlot('gk', 'Goleiro', 'GOL', 50, 92)];

        $ys = match ($count) {
            3 => [72, 46, 20],
            4 => [74, 56, 38, 18],
            default => throw new InvalidArgumentException("Formação inválida: {$code}."),
        };

        foreach ($lines as $index => $players) {
            $role = match (true) {
                $index === 0 => 'defense',
                $index === $count - 1 => 'attack',
                $count === 4 && $index === 1 => 'holding',
                $count === 4 => 'attacking_mid',
                default => 'midfield',
            };
            $prefix = ['defense' => 'd', 'holding' => 'v', 'midfield' => 'm', 'attacking_mid' => 'a', 'attack' => 'f'][$role];

            foreach (self::lineLabels($role, $players) as $position => [$label, $abbr]) {
                $x = $players === 1 ? 50 : (int) round(15 + $position * (70 / ($players - 1)));
                $slots[] = new FormationSlot($prefix.($position + 1), $label, $abbr, $x, $ys[$index]);
            }
        }

        return new Formation($code, $code, $slots);
    }

    /**
     * @return list<array{string, string}> rótulo e sigla de cada jogador da linha, da esquerda para a direita
     */
    private static function lineLabels(string $role, int $players): array
    {
        $zag = ['Zagueiro', 'ZAG'];
        $vol = ['Volante', 'VOL'];

        return match ($role) {
            'defense' => match ($players) {
                3 => [$zag, $zag, $zag],
                4 => [['Lateral esquerdo', 'LE'], $zag, $zag, ['Lateral direito', 'LD']],
                5 => [['Ala esquerdo', 'AE'], $zag, $zag, $zag, ['Ala direito', 'AD']],
                default => array_fill(0, $players, $zag),
            },
            'holding' => array_fill(0, $players, $vol),
            'midfield' => match ($players) {
                2 => [$vol, $vol],
                3 => [['Meia esquerda', 'ME'], ['Meio-campo', 'MC'], ['Meia direita', 'MD']],
                4 => [['Meia esquerda', 'ME'], ['Meio-campo', 'MC'], ['Meio-campo', 'MC'], ['Meia direita', 'MD']],
                5 => [['Ala esquerdo', 'AE'], ['Meio-campo', 'MC'], ['Meio-campo', 'MC'], ['Meio-campo', 'MC'], ['Ala direito', 'AD']],
                default => array_fill(0, $players, ['Meio-campo', 'MC']),
            },
            'attacking_mid' => match ($players) {
                3 => [['Meia esquerda', 'ME'], ['Meia-atacante', 'MEI'], ['Meia direita', 'MD']],
                4 => [['Meia esquerda', 'ME'], ['Meia-atacante', 'MEI'], ['Meia-atacante', 'MEI'], ['Meia direita', 'MD']],
                default => array_fill(0, $players, ['Meia-atacante', 'MEI']),
            },
            default => match ($players) {
                1 => [['Centroavante', 'CA']],
                2 => [['Atacante', 'ATA'], ['Atacante', 'ATA']],
                3 => [['Ponta esquerda', 'PE'], ['Centroavante', 'CA'], ['Ponta direita', 'PD']],
                default => array_fill(0, $players, ['Atacante', 'ATA']),
            },
        };
    }

    /** @return list<Formation> */
    private static function futsal(): array
    {
        $gk = new FormationSlot('gk', 'Goleiro', 'GOL', 50, 92);

        return [
            new Formation('1-2-1', '1-2-1 (losango)', [$gk,
                new FormationSlot('fix', 'Fixo', 'FIX', 50, 72), new FormationSlot('ale', 'Ala esquerdo', 'AE', 20, 46),
                new FormationSlot('ald', 'Ala direito', 'AD', 80, 46), new FormationSlot('piv', 'Pivô', 'PIV', 50, 20)]),
            new Formation('2-2', '2-2 (quadrado)', [$gk,
                new FormationSlot('fxe', 'Fixo esquerdo', 'FE', 28, 68), new FormationSlot('fxd', 'Fixo direito', 'FD', 72, 68),
                new FormationSlot('ale', 'Ala esquerdo', 'AE', 28, 32), new FormationSlot('ald', 'Ala direito', 'AD', 72, 32)]),
            new Formation('3-1', '3-1', [$gk,
                new FormationSlot('fxe', 'Fixo esquerdo', 'FE', 20, 64), new FormationSlot('fix', 'Fixo', 'FIX', 50, 72),
                new FormationSlot('fxd', 'Fixo direito', 'FD', 80, 64), new FormationSlot('piv', 'Pivô', 'PIV', 50, 24)]),
            new Formation('1-1-2', '1-1-2', [$gk,
                new FormationSlot('fix', 'Fixo', 'FIX', 50, 74), new FormationSlot('mei', 'Ala central', 'AC', 50, 52),
                new FormationSlot('ate', 'Ala esquerdo', 'AE', 28, 24), new FormationSlot('atd', 'Ala direito', 'AD', 72, 24)]),
            new Formation('4-0', '4-0 (sem pivô)', [$gk,
                new FormationSlot('l1', 'Linha 1', 'L1', 18, 48), new FormationSlot('l2', 'Linha 2', 'L2', 39, 48),
                new FormationSlot('l3', 'Linha 3', 'L3', 61, 48), new FormationSlot('l4', 'Linha 4', 'L4', 82, 48)]),
        ];
    }

    private static function basketball(): Formation
    {
        return new Formation('quinteto', 'Quinteto base', [
            new FormationSlot('pg', 'Armador', '1', 50, 78), new FormationSlot('sg', 'Ala-armador', '2', 82, 56),
            new FormationSlot('sf', 'Ala', '3', 18, 56), new FormationSlot('pf', 'Ala-pivô', '4', 66, 26),
            new FormationSlot('c', 'Pivô', '5', 34, 26),
        ]);
    }

    /** @param  list<string>  $roles função de quem começa em cada posição (1 a 6) no sistema */
    private static function volleyball(string $code, string $label, array $roles): Formation
    {
        // Posições da quadra: 4 3 2 na rede (frente) e 5 6 1 no fundo; 1 é o sacador.
        $zones = [1 => [80, 72], 2 => [80, 28], 3 => [50, 28], 4 => [20, 28], 5 => [20, 72], 6 => [50, 72]];
        $slots = [];

        foreach ($zones as $zone => [$x, $y]) {
            $role = $roles[$zone - 1];
            $slots[] = new FormationSlot("z{$zone}", "Posição {$zone} · {$role}", (string) $zone, $x, $y);
        }

        $slots[] = new FormationSlot('lib', 'Líbero', 'LIB', 94, 50, bench: true);

        return new Formation($code, $label, $slots);
    }
}
