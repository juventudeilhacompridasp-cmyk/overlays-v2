<?php

namespace App\Modules\Broadcast\Support;

/**
 * Estilo dos overlays de campeonato guardado em "Meus modelos". Espelha `obsConfig` do front (mesmas chaves, listas e
 * faixas): o que não for válido é descartado, então um JSON importado nunca grava nada fora do contrato.
 */
final class ChampionshipStyle
{
    public const STYLES = ['broadcast', 'glass', 'neon', 'light', 'minimal', 'gradient', 'stripe'];

    public const ANIMATIONS = ['rise', 'slide', 'zoom', 'wipe', 'fade', 'flip', 'cascade', 'bounce', 'drop'];

    public const POSITIONS = ['tl', 'tc', 'tr', 'cl', 'cc', 'cr', 'bl', 'bc', 'br'];

    /** Chaves numéricas: [mínimo, máximo]. */
    private const NUMBERS = [
        'surface' => [30, 100], 'radius' => [0, 28], 'scale' => [50, 200], 'bw' => [0, 12], 'sh' => [0, 80], 'pad' => [50, 220], 'blur' => [0, 30],
        'hold' => [0, 600], 'gap' => [3, 900], 'rotate' => [3, 120], 'speed' => [20, 400], 'refresh' => [5, 300],
    ];

    /**
     * @param  array<mixed>  $options
     * @return array<string, string|int>
     */
    public static function sanitize(array $options): array
    {
        $clean = [];

        foreach (['style' => self::STYLES, 'anim' => self::ANIMATIONS, 'pos' => self::POSITIONS, 'font' => ['global', ...array_diff(OverlayAppearance::FONTS, ['system'])]] as $key => $allowed) {
            if (isset($options[$key]) && is_string($options[$key]) && in_array($options[$key], $allowed, true)) {
                $clean[$key] = $options[$key];
            }
        }

        foreach (['accent', 'bg', 'text', 'bc'] as $key) {
            if (isset($options[$key]) && is_string($options[$key]) && preg_match('/^#?[0-9a-f]{6}$/i', $options[$key]) === 1) {
                $clean[$key] = '#'.strtolower(ltrim($options[$key], '#'));
            } elseif (($options[$key] ?? null) === '') {
                $clean[$key] = '';
            }
        }

        foreach (self::NUMBERS as $key => [$min, $max]) {
            if (isset($options[$key]) && is_numeric($options[$key])) {
                $clean[$key] = (int) max($min, min($max, round((float) $options[$key])));
            }
        }

        foreach (['head', 'dots', 'logos'] as $key) {
            if (isset($options[$key]) && (is_bool($options[$key]) || is_numeric($options[$key]))) {
                $clean[$key] = (int) ((bool) $options[$key]);
            }
        }

        return $clean;
    }
}
