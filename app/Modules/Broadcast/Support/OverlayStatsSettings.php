<?php

namespace App\Modules\Broadcast\Support;

/**
 * Estatísticas da partida no ar, lançadas à mão pelo operador (a súmula não guarda finalizações, escanteios etc.): números
 * por equipe, posse de bola do mandante, três visões (comparativo, gols e cartões, destaque do atleta) e o visual da tela.
 * Os números e o atleta valem para o jogo no ar: trocar de jogo zera.
 */
final class OverlayStatsSettings
{
    /** Indicadores lançáveis; a posse é um número só (a do visitante é 100 menos a do mandante). */
    public const NUMBERS = ['shots', 'shots_on_target', 'corners', 'fouls', 'offsides', 'saves'];

    public const METRICS = ['possession', ...self::NUMBERS];

    public const VIEWS = ['comparative', 'events', 'player'];

    public const STYLES = ['broadcast', 'glass', 'minimal'];

    public const ANIMATIONS = ['rise', 'slide', 'zoom', 'wipe', 'fade'];

    public const MAX_METRICS = 6;

    public const LABELS = [
        'possession' => 'Posse de bola', 'shots' => 'Finalizações', 'shots_on_target' => 'Finalizações no gol', 'corners' => 'Escanteios',
        'fouls' => 'Faltas', 'offsides' => 'Impedimentos', 'saves' => 'Defesas',
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $zero = array_fill_keys(self::NUMBERS, 0);

        return [
            'home' => $zero, 'away' => $zero, 'possession' => 50,
            'view' => 'comparative', 'metrics' => ['possession', 'shots', 'shots_on_target', 'corners', 'fouls'],
            'player' => null, 'note' => '',
            'style' => 'broadcast', 'animation' => 'rise', 'duration' => 12, 'scale' => 100, 'text_scale' => 100, 'x' => null, 'y' => null,
            'expires_ms' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function normalize(?array $settings): array
    {
        $defaults = self::defaults();
        $values = array_merge($defaults, array_intersect_key($settings ?? [], $defaults));
        $number = fn (mixed $value, int $min, int $max, int $fallback): int => is_numeric($value) ? min($max, max($min, (int) round((float) $value))) : $fallback;
        $choice = fn (mixed $value, array $allowed, string $fallback): string => is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
        $team = fn (mixed $row): array => collect(self::NUMBERS)->mapWithKeys(fn (string $key) => [$key => $number(is_array($row) ? ($row[$key] ?? 0) : 0, 0, 999, 0)])->all();
        $metrics = is_array($values['metrics']) ? array_values(array_slice(array_unique(array_intersect(array_map('strval', $values['metrics']), self::METRICS)), 0, self::MAX_METRICS)) : $defaults['metrics'];
        $free = is_numeric($values['x'] ?? null) && is_numeric($values['y'] ?? null);

        return [
            'home' => $team($values['home']),
            'away' => $team($values['away']),
            'possession' => $number($values['possession'], 0, 100, 50),
            'view' => $choice($values['view'], self::VIEWS, 'comparative'),
            'metrics' => $metrics,
            'player' => is_string($values['player']) && $values['player'] !== '' ? mb_substr($values['player'], 0, 40) : null,
            'note' => mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $values['note'])), 0, 120),
            'style' => $choice($values['style'], self::STYLES, 'broadcast'),
            'animation' => $choice($values['animation'], self::ANIMATIONS, 'rise'),
            'duration' => $number($values['duration'], 0, 120, 12),
            'scale' => $number($values['scale'], 50, 200, 100),
            'text_scale' => $number($values['text_scale'], 50, 200, 100),
            'x' => $free ? $number($values['x'], 0, 100, 0) : null,
            'y' => $free ? $number($values['y'], 0, 100, 0) : null,
            'expires_ms' => is_numeric($values['expires_ms']) && (int) $values['expires_ms'] > 0 ? (int) $values['expires_ms'] : null,
        ];
    }

    /**
     * Aplica o comando do operador por cima do que já estava salvo: os números de cada equipe são mesclados indicador a indicador.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $command
     * @return array<string, mixed>
     */
    public static function apply(?array $current, array $command): array
    {
        $next = self::normalize($current);

        foreach (['home', 'away'] as $side) {
            if (isset($command[$side]) && is_array($command[$side])) {
                $next[$side] = array_merge($next[$side], array_intersect_key($command[$side], array_flip(self::NUMBERS)));
            }
        }

        $plain = array_diff_key($command, array_flip(['home', 'away', 'expires_ms']));

        return self::normalize(array_merge($next, $plain));
    }

    /** O que o jogo no ar zera ao trocar de jogo: números, posse, atleta e prazo (o visual fica). */
    public static function forNewGame(?array $current): array
    {
        $defaults = self::defaults();

        return self::normalize(array_merge(self::normalize($current), array_intersect_key($defaults, array_flip(['home', 'away', 'possession', 'player', 'note', 'expires_ms']))));
    }

    /** Liga a tela: com duração, ela sai sozinha depois; 0 deixa no ar até o operador ocultar. */
    public static function withExpiry(?array $current, bool $on, int $nowMs): array
    {
        $normalized = self::normalize($current);
        $normalized['expires_ms'] = $on && $normalized['duration'] > 0 ? $nowMs + $normalized['duration'] * 1000 : null;

        return $normalized;
    }
}
