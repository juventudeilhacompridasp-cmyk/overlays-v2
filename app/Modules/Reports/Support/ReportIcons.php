<?php

namespace App\Modules\Reports\Support;

/**
 * Ícones dos relatórios: caminhos numa grade de 24 x 24 (y para baixo), só com M, L, C e Z absolutos, preenchidos pela
 * regra "nonzero" (furos = contorno no sentido contrário). É a MESMA fonte para o PDF (convertido em operadores de caminho)
 * e para as telas (SVG), então o relatório baixado e o overlay mostram o mesmo desenho.
 */
final class ReportIcons
{
    public const KEYS = [
        'ball', 'card', 'swap', 'trophy', 'clock', 'chart', 'shield', 'target', 'calendar', 'users',
        'percent', 'whistle', 'flag', 'star', 'home', 'away', 'equal', 'bolt', 'list', 'pin',
    ];

    /** Caminho do ícone; chave desconhecida vira o gráfico. */
    public static function path(string $key): string
    {
        return match ($key) {
            'ball' => self::circle(12, 12, 10).self::circle(12, 12, 8.2, true).self::poly([[12, 7], [16.75, 10.45], [14.94, 16.05], [9.06, 16.05], [7.25, 10.45]]),
            'card' => self::poly([[7, 3.5], [16.5, 2], [19, 19.5], [9.5, 21]]),
            'swap' => self::poly([[3, 7], [15, 7], [15, 4], [21, 8.5], [15, 13], [15, 10], [3, 10]]).self::poly([[21, 14], [21, 17], [9, 17], [9, 20], [3, 15.5], [9, 11], [9, 14]]),
            'trophy' => 'M6 3L18 3L18 9C18 12.3 15.3 15 12 15C8.7 15 6 12.3 6 9Z'
                .self::rect(10.5, 15, 3, 3).self::rect(7, 18, 10, 3)
                .'M2 4L6 4L6 6L4 6C4 7.8 4.9 9 6.3 9.6L6.6 11.4C4 10.8 2 8.5 2 6Z'
                .'M22 4L22 6C22 8.5 20 10.8 17.4 11.4L17.7 9.6C19.1 9 20 7.8 20 6L18 6L18 4Z',
            'clock' => self::circle(12, 12, 10).self::circle(12, 12, 8, true).self::poly([[11, 6], [13, 6], [13, 11.2], [16.5, 13.5], [15.4, 15.2], [11, 12.5]]),
            'chart' => self::rect(3, 19, 18, 2).self::rect(5, 12, 3, 6).self::rect(10.5, 6, 3, 12).self::rect(16, 9, 3, 9),
            'shield' => 'M12 2L20 5L20 11C20 16 16.5 20 12 22C7.5 20 4 16 4 11L4 5Z',
            'target' => self::circle(12, 12, 10).self::circle(12, 12, 7, true).self::circle(12, 12, 4).self::circle(12, 12, 1.6, true),
            'calendar' => self::rect(3, 5, 18, 16).self::rect(5, 10, 14, 9, true).self::rect(7, 2, 2, 3).self::rect(15, 2, 2, 3).self::rect(7, 12, 3, 3),
            'users' => self::circle(9, 8, 3.6).'M2 20C2 15.5 5 13 9 13C13 13 16 15.5 16 20Z'
                .self::circle(17.5, 7.5, 2.8).'M18 12C21 12 23 14 23 18L18.3 18C18.1 15.8 17.4 13.9 16.4 12.5C16.9 12.2 17.4 12 18 12Z',
            'percent' => self::circle(7, 7, 2.6).self::circle(17, 17, 2.6).self::poly([[17.5, 4], [20, 4], [6.5, 20], [4, 20]]),
            'whistle' => self::circle(8, 15, 5.5).self::poly([[8, 9.5], [21, 9.5], [21, 14], [13.5, 14]]).self::circle(8, 15, 2, true),
            'flag' => self::rect(4, 2, 2, 20).self::poly([[6, 3], [20, 3], [17, 7.5], [20, 12], [6, 12]]),
            'star' => self::star(12, 12.5, 10, 4.2),
            'home' => self::poly([[12, 3], [22, 11.5], [19, 11.5], [19, 21], [5, 21], [5, 11.5], [2, 11.5]]).self::rect(10, 14, 4, 7, true),
            'away' => self::poly([[2, 11], [22, 3], [15, 21], [11.5, 13.5]]),
            'equal' => self::rect(4, 7, 16, 3.5).self::rect(4, 13.5, 16, 3.5),
            'bolt' => self::poly([[14, 2], [5, 13.5], [11, 13.5], [9, 22], [19, 10], [13, 10], [15.5, 2]]),
            'list' => self::circle(5, 6, 2).self::rect(9, 4.8, 12, 2.4).self::circle(5, 12, 2).self::rect(9, 10.8, 12, 2.4).self::circle(5, 18, 2).self::rect(9, 16.8, 12, 2.4),
            'pin' => 'M12 2C16.4 2 20 5.4 20 9.8C20 15 12 22 12 22C12 22 4 15 4 9.8C4 5.4 7.6 2 12 2Z'.self::circle(12, 9.8, 3, true),
            default => self::path('chart'),
        };
    }

    /**
     * Os caminhos dos ícones usados num documento (vai junto do relatório para as telas desenharem sem outra consulta).
     *
     * @param  iterable<string>  $keys
     * @return array<string, string>
     */
    public static function for(iterable $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $key = in_array($key, self::KEYS, true) ? $key : 'chart';
            $out[$key] ??= self::path($key);
        }

        ksort($out);

        return $out;
    }

    private static function circle(float $cx, float $cy, float $r, bool $hole = false): string
    {
        $k = $r * 0.5523;
        $n = fn (float $v): string => self::num($v);

        if ($hole) {
            return 'M'.$n($cx + $r).' '.$n($cy)
                .'C'.$n($cx + $r).' '.$n($cy - $k).' '.$n($cx + $k).' '.$n($cy - $r).' '.$n($cx).' '.$n($cy - $r)
                .'C'.$n($cx - $k).' '.$n($cy - $r).' '.$n($cx - $r).' '.$n($cy - $k).' '.$n($cx - $r).' '.$n($cy)
                .'C'.$n($cx - $r).' '.$n($cy + $k).' '.$n($cx - $k).' '.$n($cy + $r).' '.$n($cx).' '.$n($cy + $r)
                .'C'.$n($cx + $k).' '.$n($cy + $r).' '.$n($cx + $r).' '.$n($cy + $k).' '.$n($cx + $r).' '.$n($cy).'Z';
        }

        return 'M'.$n($cx + $r).' '.$n($cy)
            .'C'.$n($cx + $r).' '.$n($cy + $k).' '.$n($cx + $k).' '.$n($cy + $r).' '.$n($cx).' '.$n($cy + $r)
            .'C'.$n($cx - $k).' '.$n($cy + $r).' '.$n($cx - $r).' '.$n($cy + $k).' '.$n($cx - $r).' '.$n($cy)
            .'C'.$n($cx - $r).' '.$n($cy - $k).' '.$n($cx - $k).' '.$n($cy - $r).' '.$n($cx).' '.$n($cy - $r)
            .'C'.$n($cx + $k).' '.$n($cy - $r).' '.$n($cx + $r).' '.$n($cy - $k).' '.$n($cx + $r).' '.$n($cy).'Z';
    }

    private static function rect(float $x, float $y, float $w, float $h, bool $hole = false): string
    {
        $points = [[$x, $y], [$x + $w, $y], [$x + $w, $y + $h], [$x, $y + $h]];

        return self::poly($hole ? array_reverse($points) : $points);
    }

    private static function star(float $cx, float $cy, float $outer, float $inner): string
    {
        $points = [];

        for ($i = 0; $i < 10; $i++) {
            $angle = M_PI / 5 * $i;
            $radius = $i % 2 === 0 ? $outer : $inner;
            $points[] = [$cx + $radius * sin($angle), $cy - $radius * cos($angle)];
        }

        return self::poly($points);
    }

    /** @param  list<array{0: float|int, 1: float|int}>  $points */
    private static function poly(array $points): string
    {
        $out = '';

        foreach ($points as $index => [$x, $y]) {
            $out .= ($index === 0 ? 'M' : 'L').self::num((float) $x).' '.self::num((float) $y);
        }

        return $out.'Z';
    }

    private static function num(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
