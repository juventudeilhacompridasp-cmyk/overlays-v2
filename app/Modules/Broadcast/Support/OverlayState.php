<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayFont;
use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Rules\Tactics\Formations;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\RosterEntry;

/**
 * Estado completo que as telas do OBS desenham (o cliente só substitui o estado). O que está no ar é decisão do
 * operador: escalação e tabela só vêm preenchidas quando a camada correspondente está ligada. Cartões, disciplina
 * e documentos nunca entram aqui.
 */
final class OverlayState
{
    /** @return array<string, mixed> */
    public static function for(OverlayChannel $channel): array
    {
        $game = $channel->game_id ? Game::query()->find($channel->game_id) : null;

        return [
            'channel' => $channel->name,
            'appearance' => $appearance = OverlayAppearance::normalize($channel->appearance),
            'font_face' => self::fontFace($channel, (string) $appearance['font']),
            'lineup_settings' => OverlayLineupSettings::public($channel->lineup_settings),
            'lower_third_settings' => OverlayLowerThirdSettings::normalize($channel->lower_third_settings),
            'sponsors' => OverlaySponsorSettings::public($channel->sponsor_settings, OverlayLineupSettings::normalize($channel->lineup_settings)['sponsor_banner']),
            'layers' => $channel->layerStates(),
            'championship' => $channel->championshipLive(),
            'screens' => $screens = $channel->screensLive(),
            'cartela' => array_diff_key(OverlayScreenSettings::normalize($channel->screen_settings), ['stoppage' => 0, 'mvp' => 0, 'social' => 0]),
            'social' => OverlayScreenSettings::normalize($channel->screen_settings)['social'],
            'stoppage' => OverlayScreenSettings::normalize($channel->screen_settings)['stoppage'],
            'compare' => $game && $screens['compare'] ? OverlayCompare::for($game) : null,
            'summary' => $game && $screens['summary'] ? OverlaySummary::for($game, OverlayScreenSettings::normalize($channel->screen_settings)['mvp']) : null,
            'bracket' => $game && $screens['bracket'] ? OverlayBracket::for($game) : null,
            'game' => $game ? PublicGameSnapshot::for($game) : null,
            'lineup' => $game && $channel->layerOn('lineup') ? self::lineup($game, OverlayLineupSettings::normalize($channel->lineup_settings)['photo_person_ids']) : null,
            'standings' => $game && $channel->layerOn('standings') ? self::standings($game) : null,
            'server_time_ms' => (int) (microtime(true) * 1000),
        ];
    }

    /**
     * Fonte própria escolhida pelo canal: endereço RELATIVO na mesma origem do overlay (o estado também é montado fora
     * de requisição). Fonte excluída ou inexistente não vem (o front usa a fonte padrão).
     *
     * @return array{family: string, url: string, format: string}|null
     */
    private static function fontFace(OverlayChannel $channel, string $font): ?array
    {
        if (in_array($font, OverlayAppearance::FONTS, true)) {
            return null;
        }

        $custom = OverlayFont::query()->find($font);

        return $custom === null ? null : [
            'family' => OverlayFont::FAMILY,
            'url' => route('overlay.font', ['channel' => $channel->token, 'font' => $custom->getKey()], false),
            'format' => $custom->format,
        ];
    }

    /**
     * Quantos jogadores a apresentação individual percorre na equipe escolhida: titulares, reservas (escopo `all`) e o técnico.
     * Mesma conta do front (`individuals` em `overlayLineup.ts`).
     */
    public static function individualCount(OverlayChannel $channel): int
    {
        $game = $channel->game_id ? Game::query()->find($channel->game_id) : null;
        $lineup = $game ? self::lineup($game, []) : null;

        if ($lineup === null) {
            return 0;
        }

        $settings = OverlayLineupSettings::normalize($channel->lineup_settings);
        $side = $lineup[$settings['team']];

        return count($side['starters']) + ($settings['individual_scope'] === 'all' ? count($side['bench']) : 0) + ($side['coach'] !== null ? 1 : 0);
    }

    /** @return array{home: array<string, mixed>, away: array<string, mixed>}|null */
    private static function lineup(Game $game, array $photoPersonIds): ?array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);

        if ($game->homeEntry === null || $game->awayEntry === null) {
            return null;
        }

        $photos = array_fill_keys($photoPersonIds, true);

        $side = function (Entry $entry) use ($game, $photos): array {
            $rows = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->get();
            $people = RosterEntry::query()->where('entry_id', $entry->getKey())->with('person:id,organization_id,name,nickname,birth_date,photo_path')->get()->keyBy('person_id');

            $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->first();
            $formation = $tactic?->formation !== null ? Formations::find($game->sport()->profile()->key, $tactic->formation) : null;

            $named = function (string $slot) use ($rows, $people, $formation, $photos) {
                return $rows->where('slot', LineupSlot::from($slot))
                    ->map(function (GameLineup $row) use ($people, $formation, $photos): array {
                        $roster = $people->get($row->person_id);
                        $person = $roster?->person;
                        $formationSlot = $formation?->slot((string) $row->position);

                        return [
                            'shirt' => $row->shirt_number,
                            'name' => PublicName::of($person) ?? '—',
                            'role' => $roster?->role,
                            'position' => $formationSlot?->abbr,
                            'position_label' => $formationSlot?->label,
                            'x' => $formationSlot?->x,
                            'y' => $formationSlot?->y,
                            'captain' => $row->is_captain,
                            'photo_url' => $person !== null && isset($photos[$person->getKey()]) ? OverlayPhotoUrl::for($person) : null,
                            'photo_large_url' => $person !== null && isset($photos[$person->getKey()]) ? OverlayPhotoUrl::for($person, 400) : null,
                        ];
                    })
                    ->sortBy(fn (array $item) => $item['shirt'] ?? 999)
                    ->values();
            };

            $coach = $named('staff')->first(fn (array $item) => $item['role'] === RosterRole::Coach);
            $starters = $named('starter');
            $requiredPositions = $formation === null ? 0 : count(array_filter($formation->slots, fn ($slot) => $slot->bench === false));

            return [
                'name' => $entry->team->name,
                'logo_url' => $entry->team->logoUrl(128),
                'color' => $entry->team->primary_color,
                'formation' => $formation?->label,
                'formation_ready' => $formation !== null && $starters->count() === $requiredPositions && $starters->every(fn (array $item) => $item['x'] !== null && $item['y'] !== null),
                'starters' => $starters->map(fn (array $i) => [
                    'shirt' => $i['shirt'], 'name' => $i['name'], 'position' => $i['position'], 'position_label' => $i['position_label'],
                    'x' => $i['x'], 'y' => $i['y'], 'captain' => $i['captain'], 'photo_url' => $i['photo_url'], 'photo_large_url' => $i['photo_large_url'],
                ])->all(),
                'bench' => $named('bench')->map(fn (array $i) => ['shirt' => $i['shirt'], 'name' => $i['name'], 'captain' => $i['captain'], 'photo_url' => $i['photo_url'], 'photo_large_url' => $i['photo_large_url']])->all(),
                'coach' => $coach['name'] ?? null,
            ];
        };

        return ['home' => $side($game->homeEntry), 'away' => $side($game->awayEntry)];
    }

    /** @return array{title: string, rows: list<array<string, mixed>>}|null */
    private static function standings(Game $game): ?array
    {
        $game->loadMissing(['stage', 'group']);
        $calculator = app(CalculateStandings::class);

        $entries = match (true) {
            $game->group !== null => $calculator->forGroup($game->group),
            $game->stage->type === StageType::League => $calculator->forStage($game->stage),
            default => null,
        };

        if ($entries === null) {
            return null; // mata-mata não tem tabela
        }

        return [
            'title' => data_get($game, 'group.name') ?? $game->stage->name,
            'rows' => array_map(fn (StandingEntry $item) => [
                'position' => $item->row->position,
                'team' => $item->entry->team->name,
                'logo_url' => $item->entry->team->logoUrl(128),
                'points' => $item->row->points,
                'played' => $item->row->played,
                'goal_difference' => $item->row->goalDifference(),
                'won' => $item->row->wins,
                'drawn' => $item->row->draws,
                'lost' => $item->row->losses,
                'color' => $item->entry->team->primary_color,
                'highlight' => in_array($item->entry->getKey(), [$game->home_entry_id, $game->away_entry_id], true),
            ], $entries),
        ];
    }
}
