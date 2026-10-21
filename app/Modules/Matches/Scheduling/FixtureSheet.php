<?php

namespace App\Modules\Matches\Scheduling;

use Illuminate\Support\Str;

/**
 * Lê uma tabela de jogos já definida, colada de uma planilha ou de um texto: uma linha por jogo, colunas
 * `rodada; mandante; visitante; data; hora; local; grupo` separadas por ponto e vírgula, tabulação (copiado do Excel)
 * ou ponto e vírgula. Puro: só devolve as linhas lidas e os problemas de formato; quem casa nomes com equipes
 * e estádios é a ação de importação.
 */
final class FixtureSheet
{
    /**
     * @return array{rows: list<array{line: int, round: int|null, home: string, away: string, date: string|null, time: string|null, venue: string, group: string}>, errors: list<string>}
     */
    public static function parse(string $text): array
    {
        $rows = [];
        $errors = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $index => $raw) {
            $line = $index + 1;

            if (trim($raw) === '') {
                continue;
            }

            $cells = array_map('trim', str_contains($raw, "\t") ? explode("\t", $raw) : explode(';', $raw));

            // Cabeçalho (opcional): a primeira coluna não é um número.
            if ($rows === [] && $errors === [] && ! ctype_digit($cells[0]) && Str::of($cells[0])->ascii()->lower()->startsWith('rodada')) {
                continue;
            }

            [$round, $home, $away, $date, $time, $venue, $group] = array_pad($cells, 7, '');

            if (! ctype_digit($round) || (int) $round < 1 || (int) $round > 999) {
                $errors[] = "Linha {$line}: a rodada precisa ser um número de 1 a 999.";

                continue;
            }

            if ($home === '' || $away === '') {
                $errors[] = "Linha {$line}: informe o mandante e o visitante.";

                continue;
            }

            $normalizedDate = null;
            $normalizedTime = null;

            if ($date !== '' || $time !== '') {
                $normalizedDate = self::date($date);
                $normalizedTime = self::time($time);

                if ($normalizedDate === null || $normalizedTime === null) {
                    $errors[] = "Linha {$line}: informe data (dd/mm/aaaa) e hora (hh:mm) juntas, ou deixe as duas em branco.";

                    continue;
                }
            }

            $rows[] = [
                'line' => $line,
                'round' => (int) $round,
                'home' => $home,
                'away' => $away,
                'date' => $normalizedDate,
                'time' => $normalizedTime,
                'venue' => $venue,
                'group' => $group,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** Chave de comparação de nomes: sem acento, caixa ou espaços repetidos. */
    public static function key(string $name): string
    {
        return (string) Str::of($name)->ascii()->lower()->replaceMatches('/\s+/', ' ')->trim();
    }

    private static function date(string $value): ?string
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) === 1) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    private static function time(string $value): ?string
    {
        if (preg_match('/^(\d{1,2})(?::|h)(\d{2})h?$/i', $value, $m) !== 1 || (int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
}
