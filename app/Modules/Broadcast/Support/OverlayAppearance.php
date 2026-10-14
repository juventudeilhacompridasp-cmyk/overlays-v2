<?php

namespace App\Modules\Broadcast\Support;

final class OverlayAppearance
{
    /**
     * arena = painel de alto contraste; classic = barra de TV compacta e chapada; minimal = pílula translúcida só com o essencial;
     * glass = vidro; contrast = moldura de destaque; neon = brilho na cor de destaque; ribbon = faixa dinâmica; gradient = degradê.
     */
    public const PRESETS = ['arena', 'classic', 'minimal', 'glass', 'contrast', 'neon', 'ribbon', 'gradient'];

    public const SHADOWS = ['none', 'soft', 'strong'];

    /** Como o placar entra quando a camada é ligada. */
    public const ENTRANCES = ['none', 'fade', 'slide', 'zoom', 'assemble'];

    public const GOAL_ANIMATIONS = ['typewriter', 'bounce', 'sweep'];

    /** Fontes embutidas no sistema (CSS próprio, sem CDN) além da padrão; uma fonte própria é escolhida pelo ID (ULID). */
    public const FONTS = ['system', 'inter', 'montserrat', 'oswald', 'bebas', 'anton', 'barlow', 'roboto-condensed', 'archivo', 'playfair', 'marker'];

    /** bar = barra horizontal clássica; stacked = equipes empilhadas; compact = pílula pequena; wide = faixa de ponta a ponta. */
    public const LAYOUTS = ['bar', 'stacked', 'compact', 'wide'];

    public const POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];

    public const MOTIONS = ['dynamic', 'subtle', 'none'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'preset' => 'arena',
            'accent_color' => '#14b8a6',
            'background_color' => '#0f172a',
            'text_color' => '#f8fafc',
            'position' => 'top-left',
            'scale' => 1.0,
            'motion' => 'dynamic',
            'font' => 'system',
            'layout' => 'bar',
            'scorers' => false,
            // Posição livre em % da tela (nulo = usa `position`).
            'x' => null,
            'y' => null,
            'text_scale' => 100,
            'show_badge' => true,
            'radius' => null,
            'surface' => 100,
            'accent_size' => 4,
            'shadow' => 'soft',
            'entrance' => 'fade',
            'entrance_speed' => 100,
            'period_scale' => 100,
            'extra_time_scale' => 100,
            'goal_celebration' => false,
            'goal_text' => 'GOOOL',
            'goal_word_seconds' => 2,
            'goal_team_seconds' => 2,
            'goal_animation' => 'typewriter',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $appearance
     * @return array<string, mixed>
     */
    public static function normalize(?array $appearance): array
    {
        $values = array_merge(self::defaults(), array_intersect_key($appearance ?? [], self::defaults()));

        return [
            'preset' => (string) $values['preset'],
            'accent_color' => strtolower((string) $values['accent_color']),
            'background_color' => strtolower((string) $values['background_color']),
            'text_color' => strtolower((string) $values['text_color']),
            'position' => (string) $values['position'],
            'scale' => (float) $values['scale'],
            'motion' => (string) $values['motion'],
            'font' => self::font($values['font']),
            'layout' => in_array($values['layout'], self::LAYOUTS, true) ? (string) $values['layout'] : 'bar',
            'scorers' => (bool) $values['scorers'],
            'x' => self::hasPoint($values) ? self::clamp($values['x'], 0, 100, 0) : null,
            'y' => self::hasPoint($values) ? self::clamp($values['y'], 0, 100, 0) : null,
            'text_scale' => self::clamp($values['text_scale'], 50, 200, 100),
            'show_badge' => (bool) $values['show_badge'],
            'radius' => $values['radius'] === null || $values['radius'] === '' ? null : self::clamp($values['radius'], 0, 40, 0),
            'surface' => self::clamp($values['surface'], 30, 100, 100),
            'accent_size' => self::clamp($values['accent_size'], 0, 8, 4),
            'shadow' => in_array($values['shadow'], self::SHADOWS, true) ? (string) $values['shadow'] : 'soft',
            'entrance' => in_array($values['entrance'], self::ENTRANCES, true) ? (string) $values['entrance'] : 'fade',
            'entrance_speed' => self::clamp($values['entrance_speed'], 50, 200, 100),
            'period_scale' => self::clamp($values['period_scale'], 60, 200, 100),
            'extra_time_scale' => self::clamp($values['extra_time_scale'], 60, 200, 100),
            'goal_celebration' => (bool) $values['goal_celebration'],
            'goal_text' => self::goalText($values['goal_text']),
            'goal_word_seconds' => self::clamp($values['goal_word_seconds'], 1, 6, 2),
            'goal_team_seconds' => self::clamp($values['goal_team_seconds'], 0, 6, 2),
            'goal_animation' => in_array($values['goal_animation'], self::GOAL_ANIMATIONS, true) ? (string) $values['goal_animation'] : 'typewriter',
        ];
    }

    /** @param  array<string, mixed>  $values */
    private static function hasPoint(array $values): bool
    {
        return is_numeric($values['x'] ?? null) && is_numeric($values['y'] ?? null);
    }

    private static function clamp(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) round((float) $value))) : $default;
    }

    /** Texto da comemoração: sem controle nem quebra de linha, até 20 caracteres. */
    private static function goalText(mixed $value): string
    {
        $text = is_string($value) ? trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)) : '';

        return $text === '' ? 'GOOOL' : mb_substr($text, 0, 20);
    }

    /** Fonte do catálogo ou ID (ULID, em minúsculas como o Laravel gera) de fonte própria; qualquer outra coisa volta à fonte padrão. */
    public static function font(mixed $value): string
    {
        if (is_string($value) && (in_array($value, self::FONTS, true) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value) === 1)) {
            return in_array($value, self::FONTS, true) ? $value : strtolower($value);
        }

        return 'system';
    }
}
