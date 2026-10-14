<?php

namespace App\Modules\Broadcast\Support;

final class OverlayLineupSettings
{
    /**
     * sequence = lista → esquema tático (as duas equipes); list/formation fixam uma fase; cards = grade de cartões da equipe
     * escolhida (titulares + comissão); individual = um jogador por vez; show = grade e depois cada jogador.
     */
    public const PRESENTATIONS = ['sequence', 'list', 'formation', 'cards', 'individual', 'show'];

    public const TEAMS = ['home', 'away'];

    public const INDIVIDUAL_MODES = ['auto', 'manual'];

    public const SCOPES = ['starters', 'all'];

    public const COMMANDS = ['next', 'previous', 'reset'];

    /** @return array{presentation: string, list_seconds: int, photo_person_ids: list<string>, team: string, individual_mode: string, individual_seconds: int, individual_scope: string, sponsor_banner: bool, step: int, anchor_ms: int} */
    public static function defaults(): array
    {
        return [
            'presentation' => 'sequence',
            'list_seconds' => 6,
            'photo_person_ids' => [],
            'team' => 'home',
            'individual_mode' => 'auto',
            'individual_seconds' => 6,
            'individual_scope' => 'starters',
            'sponsor_banner' => false,
            'step' => 0,
            'anchor_ms' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{presentation: string, list_seconds: int, photo_person_ids: list<string>, team: string, individual_mode: string, individual_seconds: int, individual_scope: string, sponsor_banner: bool, step: int, anchor_ms: int}
     */
    public static function normalize(?array $settings): array
    {
        $defaults = self::defaults();
        $values = array_merge($defaults, array_intersect_key($settings ?? [], $defaults));
        $choice = fn (mixed $value, array $allowed, string $fallback): string => is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
        $number = fn (mixed $value, int $fallback): int => is_numeric($value) ? (int) $value : $fallback;
        $ids = is_array($values['photo_person_ids']) ? $values['photo_person_ids'] : [];

        return [
            'presentation' => $choice($values['presentation'], self::PRESENTATIONS, $defaults['presentation']),
            'list_seconds' => min(20, max(3, $number($values['list_seconds'], $defaults['list_seconds']))),
            'photo_person_ids' => array_values(array_unique(array_filter($ids, 'is_string'))),
            'team' => $choice($values['team'], self::TEAMS, $defaults['team']),
            'individual_mode' => $choice($values['individual_mode'], self::INDIVIDUAL_MODES, $defaults['individual_mode']),
            'individual_seconds' => min(30, max(3, $number($values['individual_seconds'], $defaults['individual_seconds']))),
            'individual_scope' => $choice($values['individual_scope'], self::SCOPES, $defaults['individual_scope']),
            'sponsor_banner' => (bool) $values['sponsor_banner'],
            'step' => max(0, $number($values['step'], 0)),
            'anchor_ms' => max(0, $number($values['anchor_ms'], 0)),
        ];
    }

    /**
     * O que o OBS recebe (nunca os IDs das fotos habilitadas).
     *
     * @param  array<string, mixed>|null  $settings
     * @return array{presentation: string, list_seconds: int, team: string, individual_mode: string, individual_seconds: int, individual_scope: string, sponsor_banner: bool, step: int, anchor_ms: int}
     */
    public static function public(?array $settings): array
    {
        $normalized = self::normalize($settings);
        unset($normalized['photo_person_ids']);

        return $normalized;
    }
}
