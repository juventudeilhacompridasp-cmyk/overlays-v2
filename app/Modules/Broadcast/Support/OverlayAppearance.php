<?php

namespace App\Modules\Broadcast\Support;

final class OverlayAppearance
{
    public const PRESETS = ['arena', 'studio'];

    /** @var array<string, string> */
    public const PRESET_LABELS = [
        'arena' => 'Arena',
        'studio' => 'Studio',
    ];

    public const POSITIONS = ['top-left', 'top-center', 'top-right'];

    public const MOTIONS = ['dynamic', 'subtle', 'none'];

    /** @return array{preset: string, accent_color: string, background_color: string, text_color: string, position: string, scale: float, motion: string} */
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
        ];
    }

    /**
     * @param  array<string, mixed>|null  $appearance
     * @return array{preset: string, accent_color: string, background_color: string, text_color: string, position: string, scale: float, motion: string}
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
        ];
    }
}
