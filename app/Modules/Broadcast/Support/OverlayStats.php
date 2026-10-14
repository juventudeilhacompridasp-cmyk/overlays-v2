<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Portal\Support\PortalSettings;
use App\Modules\Teams\Models\Person;

/**
 * O que a tela de estatísticas recebe: indicadores lançados à mão (comparativo), gols e cartões do jogo (cartões só com
 * `game_cards` ligado) ou o destaque de um atleta da escalação (foto só se o operador habilitou aquele atleta). Nomes por
 * PublicName; nunca IDs, documentos ou nascimento.
 */
final class OverlayStats
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @param  list<string>  $photoPersonIds
     * @return array<string, mixed>
     */
    public static function for(Game $game, ?array $settings, array $photoPersonIds): array
    {
        $s = OverlayStatsSettings::normalize($settings);
        $out = [
            'view' => $s['view'], 'style' => $s['style'], 'animation' => $s['animation'], 'scale' => $s['scale'], 'text_scale' => $s['text_scale'],
            'x' => $s['x'], 'y' => $s['y'], 'duration' => $s['duration'], 'expires_ms' => $s['expires_ms'],
            'rows' => null, 'events' => null, 'player' => null,
        ];

        if ($s['view'] === 'comparative') {
            $out['rows'] = self::rows($s);
        } elseif ($s['view'] === 'events') {
            $out['events'] = self::events($game);
        } elseif ($s['player'] !== null) {
            $out['player'] = self::player($game, $s['player'], $s['note'], $photoPersonIds);
        }

        return $out;
    }

    /** @return list<array{key: string, label: string, home: int, away: int}> */
    private static function rows(array $s): array
    {
        return collect($s['metrics'])->map(fn (string $key): array => $key === 'possession'
            ? ['key' => $key, 'label' => OverlayStatsSettings::LABELS[$key], 'home' => $s['possession'], 'away' => 100 - $s['possession']]
            : ['key' => $key, 'label' => OverlayStatsSettings::LABELS[$key], 'home' => $s['home'][$key], 'away' => $s['away'][$key]])->values()->all();
    }

    /** @return array{goals: list<array{side: string, player: string|null, minute: int|null, own_goal: bool}>, cards: list<array{side: string, kind: string, player: string|null, minute: int|null}>|null} */
    private static function events(Game $game): array
    {
        $goals = collect(PublicGameSnapshot::for($game)['goals'])->map(fn (array $goal): array => [
            'side' => (string) $goal['side'], 'player' => $goal['player'], 'minute' => $goal['minute'], 'own_goal' => (bool) ($goal['own_goal'] ?? false),
        ])->values()->all();

        return ['goals' => $goals, 'cards' => PortalSettings::enabled('game_cards') ? OverlaySummary::cards($game) : null];
    }

    /**
     * @param  list<string>  $photoPersonIds
     * @return array<string, mixed>|null
     */
    private static function player(Game $game, string $personId, string $note, array $photoPersonIds): ?array
    {
        $info = OverlaySummary::mvp($game, $personId);

        if ($info === null) {
            return null;
        }

        $person = Person::query()->find($personId);
        $events = $game->events()->effective()->where('person_id', $personId)->get();
        $cards = PortalSettings::enabled('game_cards')
            ? ['yellow' => $events->where('type', GameEventType::YellowCard)->count(), 'red' => $events->where('type', GameEventType::RedCard)->count()]
            : null;

        return $info + [
            'goals' => $events->where('type', GameEventType::Goal)->count(),
            'cards' => $cards,
            'note' => $note,
            'photo_url' => $person !== null && in_array($personId, $photoPersonIds, true) ? OverlayPhotoUrl::for($person, 400) : null,
        ];
    }
}
