<?php

namespace App\Modules\Matches\Reading;

/** Lista, em português, o que a pessoa corrigiu na leitura (fica registrada junto da revisão). */
final class SheetReviewDiff
{
    private const LABELS = [
        'home_team' => 'Equipe da casa',
        'away_team' => 'Equipe visitante',
        'home_score' => 'Placar da casa',
        'away_score' => 'Placar do visitante',
        'notes' => 'Observações',
    ];

    /**
     * @param  array<string, mixed>  $read
     * @param  array<string, mixed>  $reviewed
     * @return list<string>
     */
    public static function changes(array $read, array $reviewed): array
    {
        $changes = [];

        foreach (self::LABELS as $key => $label) {
            if (($read[$key] ?? null) !== ($reviewed[$key] ?? null)) {
                $changes[] = "{$label}: ".self::show($read[$key] ?? null).' → '.self::show($reviewed[$key] ?? null);
            }
        }

        foreach (['lineups' => 'Escalação', 'events' => 'Lances'] as $key => $label) {
            $before = array_values((array) ($read[$key] ?? []));
            $after = array_values((array) ($reviewed[$key] ?? []));
            $changed = 0;

            foreach (array_intersect_key($after, $before) as $index => $row) {
                if (self::strip($row) !== self::strip($before[$index])) {
                    $changed++;
                }
            }

            if ($changed > 0) {
                $changes[] = "{$label}: {$changed} linha(s) corrigida(s)";
            }

            if (count($after) > count($before)) {
                $changes[] = "{$label}: ".(count($after) - count($before)).' linha(s) acrescentada(s)';
            }

            if (count($after) < count($before)) {
                $changes[] = "{$label}: ".(count($before) - count($after)).' linha(s) removida(s)';
            }
        }

        return $changes;
    }

    private static function show(mixed $value): string
    {
        return $value === null || $value === '' ? '(vazio)' : (string) $value;
    }

    /** A confiança não conta como correção humana. */
    private static function strip(mixed $row): mixed
    {
        if (is_array($row)) {
            unset($row['confidence']);
        }

        return $row;
    }
}
