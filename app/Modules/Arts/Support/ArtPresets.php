<?php

namespace App\Modules\Arts\Support;

/** Modelos prontos do sistema (ADR 0009). A organização duplica um deles para personalizar e salvar o próprio. */
final class ArtPresets
{
    /**
     * @return list<array{key: string, name: string, format: string, style: array<string, array<string, mixed>>}>
     */
    public static function all(): array
    {
        $presets = [
            ['key' => 'noite', 'name' => 'Clássico noite', 'format' => 'square', 'style' => []],
            ['key' => 'dia', 'name' => 'Clássico claro', 'format' => 'portrait', 'style' => [
                'background' => ['mode' => 'solid', 'color' => '#f8fafc', 'color2' => '#e2e8f0'],
                'palette' => ['primary' => '#047857', 'secondary' => '#0369a1', 'text' => '#0f172a', 'muted' => '#475569', 'accent' => '#b45309'],
                'type' => ['heading' => 'montserrat', 'weight' => 'black', 'uppercase' => true],
                'crest' => ['shape' => 'none'],
                'layout' => ['frame' => true, 'frame_color' => '#047857'],
            ]],
            ['key' => 'dividido', 'name' => 'Dividido em duas cores', 'format' => 'landscape', 'style' => [
                'background' => ['mode' => 'solid', 'color' => '#111827', 'pattern' => 'stripes', 'pattern_opacity' => 0.06],
                'palette' => ['primary' => '#dc2626', 'secondary' => '#2563eb', 'accent' => '#fde047'],
                'layout' => ['variant' => 'split'],
                'type' => ['heading' => 'bebas'],
                'crest' => ['shape' => 'rounded', 'size' => 30],
            ]],
            ['key' => 'poster', 'name' => 'Pôster neon', 'format' => 'portrait', 'style' => [
                'background' => ['mode' => 'solid', 'color' => '#050505', 'pattern' => 'grid', 'pattern_opacity' => 0.1],
                'palette' => ['primary' => '#22d3ee', 'secondary' => '#f472b6', 'accent' => '#a3e635'],
                'type' => ['heading' => 'anton', 'body' => 'barlow', 'weight' => 'normal', 'scale' => 1.1],
                'layout' => ['variant' => 'poster', 'frame' => true, 'frame_color' => '#22d3ee'],
                'crest' => ['shape' => 'none', 'size' => 34],
            ]],
            ['key' => 'story', 'name' => 'Story vertical', 'format' => 'story', 'style' => [
                'background' => ['angle' => 180, 'shade' => 0.25],
                'layout' => ['variant' => 'poster', 'margin' => 8],
                'type' => ['scale' => 1.15],
            ]],
            ['key' => 'minimal', 'name' => 'Minimalista', 'format' => 'link', 'style' => [
                'background' => ['mode' => 'solid', 'color' => '#ffffff'],
                'palette' => ['primary' => '#111827', 'secondary' => '#111827', 'text' => '#111827', 'muted' => '#6b7280', 'accent' => '#111827'],
                'type' => ['heading' => 'playfair', 'body' => 'inter', 'weight' => 'bold', 'uppercase' => false, 'spacing' => 0],
                'crest' => ['shape' => 'none', 'size' => 22],
                'layout' => ['align' => 'left', 'margin' => 7],
            ]],
        ];

        return array_map(fn (array $preset) => [...$preset, 'style' => ArtStyle::sanitize($preset['style'])], $presets);
    }

    /** @return array{key: string, name: string, format: string, style: array<string, array<string, mixed>>}|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }
}
