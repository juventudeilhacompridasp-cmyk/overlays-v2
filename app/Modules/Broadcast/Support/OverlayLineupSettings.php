<?php

namespace App\Modules\Broadcast\Support;

final class OverlayLineupSettings
{
    /**
     * sequence = lista → esquema tático (as duas equipes); list/formation fixam uma fase; cards = grade de cartões da equipe
     * escolhida (titulares + comissão); individual = um jogador por vez; show = grade e depois cada jogador; full = grade,
     * esquema tático da equipe e (opcional) cada jogador, com a faixa de patrocinadores fixa por baixo de todas as etapas.
     */
    public const PRESENTATIONS = ['sequence', 'list', 'formation', 'cards', 'individual', 'show', 'full'];

    /** Animação de troca entre as etapas da apresentação por equipe (a que sai e a que entra). */
    public const TRANSITIONS = ['fade', 'slide', 'wipe', 'zoom', 'flip'];

    public const TEAMS = ['home', 'away'];

    public const INDIVIDUAL_MODES = ['auto', 'manual'];

    public const SCOPES = ['starters', 'all'];

    public const COMMANDS = ['next', 'previous', 'reset'];

    /** Conceito visual das apresentações por equipe (cartões e jogador a jogador). */
    public const LAYOUTS = ['classic', 'tunnel', 'poster', 'sidebar', 'stadium', 'matchday'];

    /** Estilo do conceito `classic` (os outros conceitos têm visual próprio). */
    public const STYLES = ['editorial', 'cards', 'glass', 'premier', 'champions', 'diagonal'];

    public const ANIMATIONS = ['slide', 'wipe', 'rise', 'zoom', 'split', 'cascade'];

    /** photo = foto do atleta quando habilitada (senão o número); number = sempre o número da camisa. */
    public const FORMATION_MARKS = ['photo', 'number'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'presentation' => 'sequence',
            'list_seconds' => 6,
            'formation_seconds' => 8,
            'full_individuals' => true,
            'transition' => 'slide',
            'photo_person_ids' => [],
            'team' => 'home',
            'individual_mode' => 'auto',
            'individual_seconds' => 6,
            'individual_scope' => 'starters',
            'sponsor_banner' => false,
            'layout' => 'classic',
            'style' => 'editorial',
            'animation' => 'slide',
            'formation_mark' => 'photo',
            'formation_photo_size' => 100,
            'sponsor_size' => 100,
            // Posição livre em % da tela (nulo = centralizada como sempre).
            'x' => null,
            'y' => null,
            'surface' => 96,
            'radius' => null,
            'text_scale' => 100,
            'step' => 0,
            'anchor_ms' => 0,
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
        $choice = fn (mixed $value, array $allowed, string $fallback): string => is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
        $number = fn (mixed $value, int $fallback): int => is_numeric($value) ? (int) $value : $fallback;
        $free = is_numeric($values['x'] ?? null) && is_numeric($values['y'] ?? null);
        $ids = is_array($values['photo_person_ids']) ? $values['photo_person_ids'] : [];

        return [
            'presentation' => $choice($values['presentation'], self::PRESENTATIONS, $defaults['presentation']),
            'list_seconds' => min(20, max(3, $number($values['list_seconds'], $defaults['list_seconds']))),
            'formation_seconds' => min(30, max(3, $number($values['formation_seconds'], $defaults['formation_seconds']))),
            'full_individuals' => (bool) $values['full_individuals'],
            'transition' => $choice($values['transition'], self::TRANSITIONS, $defaults['transition']),
            'photo_person_ids' => array_values(array_unique(array_filter($ids, 'is_string'))),
            'team' => $choice($values['team'], self::TEAMS, $defaults['team']),
            'individual_mode' => $choice($values['individual_mode'], self::INDIVIDUAL_MODES, $defaults['individual_mode']),
            'individual_seconds' => min(30, max(3, $number($values['individual_seconds'], $defaults['individual_seconds']))),
            'individual_scope' => $choice($values['individual_scope'], self::SCOPES, $defaults['individual_scope']),
            'sponsor_banner' => (bool) $values['sponsor_banner'],
            'layout' => $choice($values['layout'], self::LAYOUTS, $defaults['layout']),
            'style' => $choice($values['style'], self::STYLES, $defaults['style']),
            'animation' => $choice($values['animation'], self::ANIMATIONS, $defaults['animation']),
            'formation_mark' => $choice($values['formation_mark'], self::FORMATION_MARKS, $defaults['formation_mark']),
            'formation_photo_size' => min(200, max(60, $number($values['formation_photo_size'], 100))),
            'sponsor_size' => min(180, max(60, $number($values['sponsor_size'], 100))),
            'x' => $free ? min(100, max(0, $number($values['x'], 0))) : null,
            'y' => $free ? min(100, max(0, $number($values['y'], 0))) : null,
            'surface' => min(100, max(40, $number($values['surface'], 96))),
            'radius' => $values['radius'] === null || $values['radius'] === '' ? null : min(40, max(0, $number($values['radius'], 0))),
            'text_scale' => min(200, max(50, $number($values['text_scale'], 100))),
            'step' => max(0, $number($values['step'], 0)),
            'anchor_ms' => max(0, $number($values['anchor_ms'], 0)),
        ];
    }

    /**
     * O que o OBS recebe (nunca os IDs das fotos habilitadas).
     *
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function public(?array $settings): array
    {
        $normalized = self::normalize($settings);
        unset($normalized['photo_person_ids']);

        return $normalized;
    }
}
