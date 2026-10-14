<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlaySponsor;

/** Rotação de patrocinadores por canal: ligada/desligada e segundos de cada logotipo no ar. */
final class OverlaySponsorSettings
{
    /** @return array{enabled: bool, seconds: int} */
    public static function defaults(): array
    {
        return ['enabled' => false, 'seconds' => 8];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{enabled: bool, seconds: int}
     */
    public static function normalize(?array $settings): array
    {
        $values = array_merge(self::defaults(), array_intersect_key($settings ?? [], self::defaults()));
        $seconds = is_numeric($values['seconds']) ? (int) $values['seconds'] : self::defaults()['seconds'];

        return ['enabled' => (bool) $values['enabled'], 'seconds' => min(30, max(3, $seconds))];
    }

    /**
     * O que o OBS recebe: com a rotação desligada, nenhum logotipo (a tela fica vazia) — a menos que a escalação peça a faixa
     * de patrocínio (`$withItems`); a tela de patrocinadores continua respeitando `enabled`.
     *
     * @param  array<string, mixed>|null  $settings
     * @return array{enabled: bool, seconds: int, items: list<array{name: string, logo_url: string}>}
     */
    public static function public(?array $settings, bool $withItems = false): array
    {
        $normalized = self::normalize($settings);
        $items = [];

        if ($normalized['enabled'] || $withItems) {
            $items = OverlaySponsor::query()->where('active', true)->orderBy('sort')->orderBy('created_at')->get()
                ->map(fn (OverlaySponsor $sponsor) => ['name' => $sponsor->name, 'logo_url' => (string) $sponsor->logoUrl()])
                ->values()->all();
        }

        return $normalized + ['items' => $items];
    }
}
