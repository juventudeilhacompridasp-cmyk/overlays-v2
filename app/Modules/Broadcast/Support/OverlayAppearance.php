<?php

namespace App\Modules\Broadcast\Support;

final class OverlayAppearance
{
    /** arena = painel de alto contraste; classic = barra de TV compacta e chapada; minimal = pílula translúcida só com o essencial. */
    public const PRESETS = ['arena', 'classic', 'minimal'];

    /** Fontes embutidas no sistema (CSS próprio, sem CDN) além da padrão; uma fonte própria é escolhida pelo ID (ULID). */
    public const FONTS = ['system', 'inter', 'montserrat', 'oswald', 'bebas', 'anton', 'barlow', 'roboto-condensed', 'archivo', 'playfair', 'marker'];

    /** bar = barra horizontal clássica; stacked = equipes empilhadas; compact = pílula pequena; wide = faixa de ponta a ponta. */
    public const LAYOUTS = ['bar', 'stacked', 'compact', 'wide'];

    public const POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'];

    public const MOTIONS = ['dynamic', 'subtle', 'none'];

    /** @return array{preset: string, accent_color: string, background_color: string, text_color: string, position: string, scale: float, motion: string, font: string, layout: string, scorers: bool} */
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
        ];
    }

    /**
     * @param  array<string, mixed>|null  $appearance
     * @return array{preset: string, accent_color: string, background_color: string, text_color: string, position: string, scale: float, motion: string, font: string, layout: string, scorers: bool}
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
        ];
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
