<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlaySponsor;

/**
 * Elementos do overlay livre (Builder): texto, imagem e forma sobre um canvas. Tudo que vem do painel, de uma importação
 * ou da API passa por aqui, então o OBS só recebe valores dentro dos limites (cores `#rrggbb`, números limitados, listas
 * fechadas, imagem só por referência segura). Posição e tamanho em % do canvas.
 */
final class OverlayCustomElements
{
    public const TYPES = ['text', 'image', 'shape'];

    public const MAX_ELEMENTS = 60;

    public const ANIMATIONS = ['none', 'fade', 'slide-left', 'slide-right', 'slide-up', 'slide-down', 'zoom', 'pop', 'wipe', 'flip'];

    public const FONTS = ['global', 'inter', 'montserrat', 'oswald', 'bebas', 'anton', 'barlow', 'roboto-condensed', 'archivo', 'playfair', 'marker'];

    public const SIZES = [[1920, 1080], [1280, 720], [1080, 1920], [1500, 200], [1200, 260], [800, 450], [600, 600]];

    /** Imagens que o jogo no ar preenche sozinho. */
    public const IMAGE_TOKENS = ['token:home.logo', 'token:away.logo', 'token:sponsor'];

    public const NAMES = ['text' => 'Texto', 'image' => 'Imagem', 'shape' => 'Forma'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function sanitize(mixed $raw): array
    {
        $list = is_array($raw) ? array_slice(array_values($raw), 0, self::MAX_ELEMENTS) : [];
        $seen = [];
        $out = [];

        foreach ($list as $index => $element) {
            $clean = self::element(is_array($element) ? $element : [], $index);

            while (isset($seen[$clean['id']])) {
                $clean['id'] = substr($clean['id'], 0, 20).'-'.$index;
            }

            $seen[$clean['id']] = true;
            $out[] = $clean;
        }

        return $out;
    }

    /** Largura e altura do canvas (200 a 3840). */
    public static function size(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? min(3840, max(200, (int) round((float) $value))) : $fallback;
    }

    /**
     * @param  array<mixed>  $raw
     * @return array<string, mixed>
     */
    private static function element(array $raw, int $index): array
    {
        $type = in_array($raw['type'] ?? null, self::TYPES, true) ? (string) $raw['type'] : 'text';
        $id = substr((string) preg_replace('/[^a-z0-9-]/i', '', (string) ($raw['id'] ?? '')), 0, 24);
        $weight = (int) ($raw['weight'] ?? 700);
        $animation = fn (mixed $value, string $fallback): string => is_string($value) && in_array($value, self::ANIMATIONS, true) ? $value : $fallback;

        return [
            'id' => $id !== '' ? $id : 'el-'.($index + 1),
            'type' => $type,
            'name' => self::line($raw['name'] ?? self::NAMES[$type], 60) ?: self::NAMES[$type],
            'x' => self::number($raw['x'] ?? null, -100, 200, 10), 'y' => self::number($raw['y'] ?? null, -100, 200, 10),
            'w' => self::number($raw['w'] ?? null, 0.5, 300, 30), 'h' => self::number($raw['h'] ?? null, 0.5, 300, 12),
            'rotation' => self::number($raw['rotation'] ?? null, -360, 360, 0), 'opacity' => self::number($raw['opacity'] ?? null, 0, 100, 100),
            'visible' => ($raw['visible'] ?? true) !== false, 'locked' => (bool) ($raw['locked'] ?? false),
            'text' => self::text($raw['text'] ?? ($type === 'text' ? 'Texto' : ''), 400),
            'font' => is_string($raw['font'] ?? null) && in_array($raw['font'], self::FONTS, true) ? $raw['font'] : 'global',
            'size' => self::number($raw['size'] ?? null, 6, 800, 48),
            'weight' => in_array($weight, [400, 500, 600, 700, 800, 900], true) ? $weight : 700,
            'color' => self::color($raw['color'] ?? null, '#ffffff'),
            'align' => in_array($raw['align'] ?? null, ['left', 'center', 'right'], true) ? $raw['align'] : 'left',
            'valign' => in_array($raw['valign'] ?? null, ['top', 'middle', 'bottom'], true) ? $raw['valign'] : 'middle',
            'transform' => ($raw['transform'] ?? null) === 'uppercase' ? 'uppercase' : 'none',
            'spacing' => self::number($raw['spacing'] ?? null, -10, 40, 0), 'lineHeight' => self::number($raw['lineHeight'] ?? null, 70, 220, 110),
            'italic' => (bool) ($raw['italic'] ?? false), 'marquee' => (bool) ($raw['marquee'] ?? false), 'marqueeSpeed' => self::number($raw['marqueeSpeed'] ?? null, 2, 90, 14),
            'fill' => self::optionalColor($raw['fill'] ?? null), 'fill2' => self::optionalColor($raw['fill2'] ?? null),
            'fillAngle' => self::number($raw['fillAngle'] ?? null, 0, 360, 135), 'fillOpacity' => self::number($raw['fillOpacity'] ?? null, 0, 100, 100),
            'radius' => self::number($raw['radius'] ?? null, 0, 800, 0), 'borderColor' => self::color($raw['borderColor'] ?? null, '#ffffff'), 'borderWidth' => self::number($raw['borderWidth'] ?? null, 0, 80, 0),
            'shadow' => in_array($raw['shadow'] ?? null, ['none', 'soft', 'strong'], true) ? $raw['shadow'] : 'none', 'padding' => self::number($raw['padding'] ?? null, 0, 300, 0),
            'src' => self::source($raw['src'] ?? null),
            'fit' => ($raw['fit'] ?? null) === 'contain' ? 'contain' : 'cover', 'shape' => ($raw['shape'] ?? null) === 'circle' ? 'circle' : 'rect',
            'animIn' => $animation($raw['animIn'] ?? null, 'fade'),
            'animOut' => ($raw['animOut'] ?? null) === 'same' ? 'same' : $animation($raw['animOut'] ?? null, 'same'),
            'delay' => self::number($raw['delay'] ?? null, 0, 10000, 0), 'duration' => self::number($raw['duration'] ?? null, 100, 5000, 600),
        ];
    }

    /**
     * Imagem: https, `token:` do jogo no ar (escudos e patrocinador ativo) ou `sponsor:{id}` (logotipo de um patrocinador da
     * biblioteca). Qualquer outra coisa (http, data:, javascript:, caminho) é descartada.
     */
    private static function source(mixed $value): string
    {
        $src = is_string($value) ? trim($value) : '';

        if (in_array($src, self::IMAGE_TOKENS, true) || preg_match('/^sponsor:[0-9A-Za-z]{26}$/', $src) === 1) {
            return $src;
        }

        return mb_strlen($src) <= 500 && preg_match('#^https://[^\s/]+(/[^\s]*)?$#i', $src) === 1 ? $src : '';
    }

    /**
     * Troca `sponsor:{id}` pelo endereço do logotipo para o OBS (o ID nunca vai ao overlay) e devolve os elementos prontos.
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    public static function resolve(array $elements): array
    {
        $ids = collect($elements)->pluck('src')->filter(fn ($src) => is_string($src) && str_starts_with($src, 'sponsor:'))->map(fn (string $src) => substr($src, 8))->unique()->values();
        $logos = $ids->isEmpty() ? collect() : OverlaySponsor::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (OverlaySponsor $sponsor) => ['sponsor:'.$sponsor->getKey() => (string) $sponsor->logoUrl()]);

        return array_map(function (array $element) use ($logos): array {
            if (is_string($element['src']) && str_starts_with($element['src'], 'sponsor:')) {
                $element['src'] = (string) ($logos[$element['src']] ?? '');
            }

            return $element;
        }, $elements);
    }

    private static function number(mixed $value, float $min, float $max, float $fallback): int|float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $number = min($max, max($min, (float) $value));
        $rounded = round($number, 2);

        return $rounded == (int) $rounded ? (int) $rounded : $rounded;
    }

    private static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : $fallback;
    }

    private static function optionalColor(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : '';
    }

    /** Uma linha: sem controle nem quebra, no máximo `$max` caracteres. */
    private static function line(mixed $value, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', is_scalar($value) ? (string) $value : '')), 0, $max);
    }

    /** Texto do elemento: quebras de linha ficam, os demais controles saem. */
    private static function text(mixed $value, int $max): string
    {
        return mb_substr(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', is_scalar($value) ? (string) $value : '') ?? '', 0, $max);
    }
}
