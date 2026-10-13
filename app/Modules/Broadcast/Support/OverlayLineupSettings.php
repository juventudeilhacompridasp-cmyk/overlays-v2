<?php

namespace App\Modules\Broadcast\Support;

final class OverlayLineupSettings
{
    public const PRESENTATIONS = ['sequence', 'list', 'formation'];

    /** @return array{presentation: string, list_seconds: int, photo_person_ids: list<string>} */
    public static function defaults(): array
    {
        return [
            'presentation' => 'sequence',
            'list_seconds' => 6,
            'photo_person_ids' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{presentation: string, list_seconds: int, photo_person_ids: list<string>}
     */
    public static function normalize(?array $settings): array
    {
        $values = array_merge(self::defaults(), array_intersect_key($settings ?? [], self::defaults()));
        $presentation = is_string($values['presentation']) && in_array($values['presentation'], self::PRESENTATIONS, true)
            ? $values['presentation']
            : self::defaults()['presentation'];
        $seconds = is_numeric($values['list_seconds']) ? (int) $values['list_seconds'] : self::defaults()['list_seconds'];
        $ids = is_array($values['photo_person_ids']) ? $values['photo_person_ids'] : [];

        return [
            'presentation' => $presentation,
            'list_seconds' => min(20, max(3, $seconds)),
            'photo_person_ids' => array_values(array_unique(array_filter($ids, 'is_string'))),
        ];
    }

    /** @return array{presentation: string, list_seconds: int} */
    public static function public(?array $settings): array
    {
        $normalized = self::normalize($settings);

        return [
            'presentation' => $normalized['presentation'],
            'list_seconds' => $normalized['list_seconds'],
        ];
    }
}
