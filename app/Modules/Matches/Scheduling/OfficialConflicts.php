<?php

namespace App\Modules\Matches\Scheduling;

/**
 * Conflitos da escala de árbitros e mesários (puro, sem banco): a mesma pessoa escalada em dois jogos com menos de
 * `$minutes` entre os horários. Só avisa; quem decide é o organizador.
 */
final class OfficialConflicts
{
    public const WINDOW_MINUTES = 150;

    /**
     * @param  list<array{official: string, name: string, game: string, at: int, label: string}>  $rows  escalas (jogo com horário) da janela
     * @param  list<string>  $targets  jogos que a tela mostra
     * @return array<string, list<string>> avisos por jogo
     */
    public static function find(array $rows, array $targets, int $minutes = self::WINDOW_MINUTES): array
    {
        $window = $minutes * 60;
        $targetSet = array_fill_keys($targets, true);
        $found = [];

        foreach ($rows as $row) {
            if (! isset($targetSet[$row['game']])) {
                continue;
            }

            foreach ($rows as $other) {
                if ($other['official'] !== $row['official'] || $other['game'] === $row['game'] || abs($other['at'] - $row['at']) >= $window) {
                    continue;
                }

                $message = "{$row['name']} também está escalado em {$other['label']}.";
                $found[$row['game']] ??= [];

                if (! in_array($message, $found[$row['game']], true)) {
                    $found[$row['game']][] = $message;
                }
            }
        }

        return $found;
    }
}
