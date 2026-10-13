<?php

namespace App\Modules\Arts\Support;

/**
 * Estilo de uma arte (ADR 0009): um JSON com TODAS as opções, sempre completo e saneado. `sanitize` parte do padrão e só
 * aceita, de cada chave, valores válidos (cores #rgb/#rrggbb, números dentro da faixa, listas fechadas); o resto volta ao
 * padrão — nunca erro, nunca HTML. É a fonte da verdade dos padrões: o front só desenha o que recebe.
 */
final class ArtStyle
{
    /** Fontes do catálogo: as do sistema (pilhas seguras) e as web embutidas no front (`artFonts.ts`). */
    public const FONTS = [
        'inter' => 'Inter (moderna)',
        'montserrat' => 'Montserrat (geométrica)',
        'oswald' => 'Oswald (condensada)',
        'bebas' => 'Bebas Neue (títulos altos)',
        'anton' => 'Anton (títulos pesados)',
        'barlow' => 'Barlow Condensed',
        'roboto-condensed' => 'Roboto Condensed',
        'archivo' => 'Archivo Black (extra pesada)',
        'playfair' => 'Playfair Display (clássica)',
        'marker' => 'Permanent Marker (manuscrita)',
        'sans' => 'Sistema: sem serifa',
        'display' => 'Sistema: Impact',
        'condensed' => 'Sistema: condensada',
        'serif' => 'Sistema: Georgia',
        'mono' => 'Sistema: monoespaçada',
    ];

    public const VARIANTS = ['classic' => 'Clássico (centralizado)', 'split' => 'Dividido (duas metades)', 'poster' => 'Pôster (escudos grandes)'];

    public const PATTERNS = ['none' => 'Nenhum', 'stripes' => 'Listras', 'grid' => 'Grade', 'dots' => 'Pontos'];

    public const CREST_SHAPES = ['none' => 'Sem fundo', 'circle' => 'Círculo', 'rounded' => 'Quadrado arredondado', 'badge' => 'Placa'];

    public const WEIGHTS = ['normal' => 'Normal', 'bold' => 'Negrito', 'black' => 'Extra negrito'];

    public const ELEMENTS = [
        'competition' => 'Nome da competição',
        'phase' => 'Fase / rodada',
        'date' => 'Data',
        'time' => 'Horário',
        'venue' => 'Local',
        'status' => 'Situação do jogo',
        'org_logo' => 'Logotipo da organização',
        'footer' => 'Rodapé',
        'broadcast' => 'Rodapé de transmissão',
    ];

    public const PLATFORMS = ['none' => 'Nenhuma (só texto)', 'youtube' => 'YouTube', 'twitch' => 'Twitch', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'kick' => 'Kick'];

    /** @return array<string, array<string, mixed>> */
    public static function defaults(): array
    {
        return [
            'background' => ['mode' => 'gradient', 'color' => '#0b1220', 'color2' => '#16243d', 'angle' => 160, 'pattern' => 'none', 'pattern_opacity' => 0.08, 'shade' => 0.0, 'image' => null, 'image_fit' => 'cover', 'image_opacity' => 1.0, 'tint' => '#0d6fd6', 'tint_opacity' => 0.0],
            'logo' => ['asset' => null, 'size' => 8.0, 'position' => 'corner'],
            'palette' => ['primary' => '#10b981', 'secondary' => '#38bdf8', 'text' => '#ffffff', 'muted' => '#94a3b8', 'accent' => '#facc15'],
            'type' => ['heading' => 'oswald', 'body' => 'inter', 'scale' => 1.0, 'weight' => 'bold', 'uppercase' => true, 'spacing' => 0.02],
            'layout' => ['variant' => 'classic', 'align' => 'center', 'margin' => 6.0, 'frame' => false, 'frame_width' => 0.6, 'frame_color' => '#ffffff', 'radius' => 0.0],
            'crest' => ['show' => true, 'size' => 26.0, 'shape' => 'circle', 'bg' => '#ffffff', 'use_team_colors' => false],
            'elements' => [...array_fill_keys(array_keys(self::ELEMENTS), true), 'broadcast' => false],
            'broadcast' => ['platform' => 'youtube', 'label' => 'Transmissão ao vivo', 'channel' => '', 'badge' => null],
            'text' => ['title' => '', 'subtitle' => '', 'headline' => '', 'credit' => '', 'footer' => '', 'hashtag' => ''],
        ];
    }

    /**
     * @param  array<string, mixed>  $input  qualquer entrada (JSON do cliente)
     * @return array<string, array<string, mixed>>
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $in = fn (string $group): array => is_array($input[$group] ?? null) ? $input[$group] : [];

        $bg = $in('background');
        $pal = $in('palette');
        $type = $in('type');
        $lay = $in('layout');
        $crest = $in('crest');
        $el = $in('elements');
        $text = $in('text');
        $bc = $in('broadcast');
        $logo = $in('logo');

        return [
            'background' => [
                'mode' => self::choice($bg['mode'] ?? null, ['solid', 'gradient'], $d['background']['mode']),
                'color' => self::color($bg['color'] ?? null, $d['background']['color']),
                'color2' => self::color($bg['color2'] ?? null, $d['background']['color2']),
                'angle' => self::number($bg['angle'] ?? null, 0, 360, $d['background']['angle']),
                'pattern' => self::choice($bg['pattern'] ?? null, array_keys(self::PATTERNS), $d['background']['pattern']),
                'pattern_opacity' => self::number($bg['pattern_opacity'] ?? null, 0, 0.4, $d['background']['pattern_opacity']),
                'shade' => self::number($bg['shade'] ?? null, 0, 0.9, $d['background']['shade']),
                'image' => self::assetId($bg['image'] ?? null),
                'image_fit' => self::choice($bg['image_fit'] ?? null, ['cover', 'contain'], $d['background']['image_fit']),
                'image_opacity' => self::number($bg['image_opacity'] ?? null, 0, 1, $d['background']['image_opacity']),
                'tint' => self::color($bg['tint'] ?? null, $d['background']['tint']),
                'tint_opacity' => self::number($bg['tint_opacity'] ?? null, 0, 1, $d['background']['tint_opacity']),
            ],
            'logo' => [
                'asset' => self::assetId($logo['asset'] ?? null),
                'size' => self::number($logo['size'] ?? null, 3, 30, $d['logo']['size']),
                'position' => self::choice($logo['position'] ?? null, ['corner', 'top'], $d['logo']['position']),
            ],
            'palette' => [
                'primary' => self::color($pal['primary'] ?? null, $d['palette']['primary']),
                'secondary' => self::color($pal['secondary'] ?? null, $d['palette']['secondary']),
                'text' => self::color($pal['text'] ?? null, $d['palette']['text']),
                'muted' => self::color($pal['muted'] ?? null, $d['palette']['muted']),
                'accent' => self::color($pal['accent'] ?? null, $d['palette']['accent']),
            ],
            'type' => [
                'heading' => self::choice($type['heading'] ?? null, array_keys(self::FONTS), $d['type']['heading']),
                'body' => self::choice($type['body'] ?? null, array_keys(self::FONTS), $d['type']['body']),
                'scale' => self::number($type['scale'] ?? null, 0.6, 1.6, $d['type']['scale']),
                'weight' => self::choice($type['weight'] ?? null, array_keys(self::WEIGHTS), $d['type']['weight']),
                'uppercase' => self::bool($type['uppercase'] ?? null, $d['type']['uppercase']),
                'spacing' => self::number($type['spacing'] ?? null, -0.05, 0.3, $d['type']['spacing']),
            ],
            'layout' => [
                'variant' => self::choice($lay['variant'] ?? null, array_keys(self::VARIANTS), $d['layout']['variant']),
                'align' => self::choice($lay['align'] ?? null, ['center', 'left'], $d['layout']['align']),
                'margin' => self::number($lay['margin'] ?? null, 2, 15, $d['layout']['margin']),
                'frame' => self::bool($lay['frame'] ?? null, $d['layout']['frame']),
                'frame_width' => self::number($lay['frame_width'] ?? null, 0.2, 3, $d['layout']['frame_width']),
                'frame_color' => self::color($lay['frame_color'] ?? null, $d['layout']['frame_color']),
                'radius' => self::number($lay['radius'] ?? null, 0, 10, $d['layout']['radius']),
            ],
            'crest' => [
                'show' => self::bool($crest['show'] ?? null, $d['crest']['show']),
                'size' => self::number($crest['size'] ?? null, 10, 45, $d['crest']['size']),
                'shape' => self::choice($crest['shape'] ?? null, array_keys(self::CREST_SHAPES), $d['crest']['shape']),
                'bg' => self::color($crest['bg'] ?? null, $d['crest']['bg']),
                'use_team_colors' => self::bool($crest['use_team_colors'] ?? null, $d['crest']['use_team_colors']),
            ],
            'elements' => array_combine(array_keys(self::ELEMENTS), array_map(fn (string $key) => self::bool($el[$key] ?? null, $d['elements'][$key]), array_keys(self::ELEMENTS))),
            'broadcast' => [
                'platform' => self::choice($bc['platform'] ?? null, array_keys(self::PLATFORMS), $d['broadcast']['platform']),
                'label' => array_key_exists('label', $bc) ? self::text($bc['label'], 40) : $d['broadcast']['label'],
                'channel' => self::text($bc['channel'] ?? null, 60),
                'badge' => self::assetId($bc['badge'] ?? null),
            ],
            'text' => [
                'title' => self::text($text['title'] ?? null, 60),
                'subtitle' => self::text($text['subtitle'] ?? null, 80),
                'credit' => self::text($text['credit'] ?? null, 120),
                'headline' => self::text($text['headline'] ?? null, 80),
                'footer' => self::text($text['footer'] ?? null, 120),
                'hashtag' => self::text($text['hashtag'] ?? null, 40),
            ],
        ];
    }

    /** Identificador de uma imagem da organização (ULID) ou null. */
    private static function assetId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9A-Za-z]{26}$/', $value) === 1 ? $value : null;
    }

    /** @param  list<string>  $allowed */
    private static function choice(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private static function color(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1 ? strtolower($value) : $default;
    }

    private static function number(mixed $value, float $min, float $max, float|int $default): float
    {
        return is_numeric($value) ? round(max($min, min((float) $value, $max)), 3) : (float) $default;
    }

    private static function bool(mixed $value, bool $default): bool
    {
        return is_bool($value) ? $value : $default;
    }

    /** Texto livre: sem quebra de linha nem caracteres de controle, no máximo `$max` caracteres. */
    private static function text(mixed $value, int $max): string
    {
        if (! is_string($value)) {
            return '';
        }

        $clean = preg_replace('/[\p{Cc}\p{Cf}\s]+/u', ' ', $value) ?? '';

        return mb_substr(trim($clean), 0, $max);
    }
}
