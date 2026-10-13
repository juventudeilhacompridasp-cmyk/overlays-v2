<?php

namespace App\Modules\Arts\Support;

/** Proporções das artes (ADR 0009): presets de redes sociais e tamanho personalizado. */
final class ArtFormats
{
    public const MIN = 200;

    public const MAX = 4000;

    public const PRESETS = [
        'square' => ['label' => 'Quadrado 1:1 (feed)', 'width' => 1080, 'height' => 1080],
        'portrait' => ['label' => 'Retrato 4:5 (feed)', 'width' => 1080, 'height' => 1350],
        'tall' => ['label' => 'Retrato 3:4', 'width' => 1080, 'height' => 1440],
        'story' => ['label' => 'Story/Reels 9:16', 'width' => 1080, 'height' => 1920],
        'landscape' => ['label' => 'Paisagem 16:9 (TV/YouTube)', 'width' => 1920, 'height' => 1080],
        'link' => ['label' => 'Link 1,91:1 (compartilhamento)', 'width' => 1200, 'height' => 630],
    ];

    /**
     * Formato pedido → `{key, width, height}`. Chave desconhecida cai no quadrado; `custom` usa largura × altura (200–4000).
     *
     * @return array{key: string, width: int, height: int}
     */
    public static function resolve(mixed $key, mixed $width = null, mixed $height = null): array
    {
        if ($key === 'custom' && is_numeric($width) && is_numeric($height)) {
            return ['key' => 'custom', 'width' => self::clamp((int) $width), 'height' => self::clamp((int) $height)];
        }

        $key = is_string($key) && isset(self::PRESETS[$key]) ? $key : 'square';

        return ['key' => $key, 'width' => self::PRESETS[$key]['width'], 'height' => self::PRESETS[$key]['height']];
    }

    /** @return list<array{value: string, label: string, width: int, height: int}> */
    public static function options(): array
    {
        return array_map(fn (string $key, array $item) => ['value' => $key, ...$item], array_keys(self::PRESETS), array_values(self::PRESETS));
    }

    private static function clamp(int $value): int
    {
        return max(self::MIN, min($value, self::MAX));
    }
}
