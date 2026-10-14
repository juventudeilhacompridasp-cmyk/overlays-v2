<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlaySponsor;

/**
 * Patrocinadores por canal: rotação (tela `patrocinadores`: formato, estilo, animação, posição) e barra larga
 * (tela `barra-patrocinio`, 1500 x 200: tempo, transição, moldura). Os logotipos e banners são da organização.
 */
final class OverlaySponsorSettings
{
    /** logo = só o logotipo; logo_name = logotipo e nome; text = só o nome; banner = banner largo; banner_name = banner e nome. */
    public const FORMATS = ['logo', 'logo_name', 'text', 'banner', 'banner_name'];

    public const STYLES = ['boxed', 'clean', 'ribbon'];

    public const ANIMATIONS = ['fade', 'slide', 'zoom', 'rise', 'flip'];

    public const BAR_TRANSITIONS = ['fade', 'slide', 'zoom', 'flip', 'elastic'];

    public const BAR_FITS = ['cover', 'contain'];

    public const BAR_BORDERS = ['none', 'thin', 'accent'];

    public const BAR_SHADOWS = ['none', 'soft', 'strong'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'enabled' => false, 'seconds' => 8,
            'format' => 'logo', 'style' => 'boxed', 'animation' => 'fade', 'animation_speed' => 100, 'scale' => 100, 'text_scale' => 100,
            'x' => null, 'y' => null,
            'bar_seconds' => 10, 'bar_transition' => 'fade', 'bar_fit' => 'cover', 'bar_border' => 'thin', 'bar_shadow' => 'none',
            'bar_scale' => 100, 'bar_x' => 50, 'bar_y' => 91, 'bar_opacity' => 100, 'bar_radius' => 0, 'bar_background' => '#08090d',
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
        $free = is_numeric($values['x'] ?? null) && is_numeric($values['y'] ?? null);
        $background = is_string($values['bar_background']) && preg_match('/^#[0-9a-fA-F]{6}$/', $values['bar_background']) === 1 ? strtolower($values['bar_background']) : $defaults['bar_background'];

        return [
            'enabled' => (bool) $values['enabled'],
            'seconds' => $number($values['seconds'], 3, 30, 8),
            'format' => $choice($values['format'], self::FORMATS, 'logo'),
            'style' => $choice($values['style'], self::STYLES, 'boxed'),
            'animation' => $choice($values['animation'], self::ANIMATIONS, 'fade'),
            'animation_speed' => $number($values['animation_speed'], 50, 200, 100),
            'scale' => $number($values['scale'], 50, 200, 100),
            'text_scale' => $number($values['text_scale'], 50, 200, 100),
            'x' => $free ? $number($values['x'], 0, 100, 0) : null,
            'y' => $free ? $number($values['y'], 0, 100, 0) : null,
            'bar_seconds' => $number($values['bar_seconds'], 3, 60, 10),
            'bar_transition' => $choice($values['bar_transition'], self::BAR_TRANSITIONS, 'fade'),
            'bar_fit' => $choice($values['bar_fit'], self::BAR_FITS, 'cover'),
            'bar_border' => $choice($values['bar_border'], self::BAR_BORDERS, 'thin'),
            'bar_shadow' => $choice($values['bar_shadow'], self::BAR_SHADOWS, 'none'),
            'bar_scale' => $number($values['bar_scale'], 50, 150, 100),
            'bar_x' => $number($values['bar_x'], 0, 100, 50),
            'bar_y' => $number($values['bar_y'], 0, 100, 91),
            'bar_opacity' => $number($values['bar_opacity'], 30, 100, 100),
            'bar_radius' => $number($values['bar_radius'], 0, 40, 0),
            'bar_background' => $background,
        ];
    }

    /**
     * O que o OBS recebe: com a rotação desligada, nenhum logotipo (a tela fica vazia) — a menos que a escalação ou a barra
     * peçam os itens (`$withItems`); a tela de patrocinadores continua respeitando `enabled`.
     *
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function public(?array $settings, bool $withItems = false): array
    {
        $normalized = self::normalize($settings);
        $items = [];

        if ($normalized['enabled'] || $withItems) {
            $items = OverlaySponsor::query()->where('active', true)->orderBy('sort')->orderBy('created_at')->get()
                ->map(fn (OverlaySponsor $sponsor) => ['name' => $sponsor->name, 'logo_url' => (string) $sponsor->logoUrl(), 'banner_url' => $sponsor->bannerUrl()])
                ->values()->all();
        }

        return $normalized + ['items' => $items];
    }
}
