<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Matches\Support\PublicGameSnapshot;
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
            'layers' => array_merge(array_fill_keys(OverlayChannel::LAYERS, false), array_map('boolval', $channel->layers ?? [])),
            'game' => $game ? PublicGameSnapshot::for($game) : null,
            'lineup' => $game && $channel->layerOn('lineup') ? self::lineup($game) : null,
            'standings' => $game && $channel->layerOn('standings') ? self::standings($game) : null,
            'server_time_ms' => (int) (microtime(true) * 1000),
        ];
    }

    /** @return array{home: array<string, mixed>, away: array<string, mixed>}|null */
    private static function lineup(Game $game): ?array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team']);

        if ($game->homeEntry === null || $game->awayEntry === null) {
            return null;
        }

        $side = function ($entry) use ($game): array {
            $rows = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->get();
            $people = RosterEntry::query()->where('entry_id', $entry->getKey())->with('person:id,name,nickname')->get()->keyBy('person_id');

            $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->first();
            $formation = $tactic?->formation !== null ? Formations::find($game->sport()->profile()->key, $tactic->formation) : null;

            $named = function (string $slot) use ($rows, $people, $formation) {
                return $rows->where('slot', LineupSlot::from($slot))
                    ->map(function (GameLineup $row) use ($people): array {
                        $roster = $people->get($row->person_id);
                        $person = $roster?->person;

                        return [
                            'shirt' => $row->shirt_number,
                            'name' => $person === null ? '—' : ($person->nickname ?: $person->name),
                            'role' => $roster?->role,
                            'position' => $formation?->slot((string) $row->position)?->abbr,
                            'captain' => $row->is_captain,
                        ];
                    })
                    ->sortBy(fn (array $item) => $item['shirt'] ?? 999)
                    ->values();
            };

            $coach = $named('staff')->first(fn (array $item) => $item['role'] === RosterRole::Coach);

            return [
                'name' => $entry->team->name,
                'logo_url' => $entry->team->logoUrl(128),
                'color' => $entry->team->primary_color,
                'formation' => $formation?->label,
                'starters' => $named('starter')->map(fn (array $i) => ['shirt' => $i['shirt'], 'name' => $i['name'], 'position' => $i['position'], 'captain' => $i['captain']])->all(),
                'bench' => $named('bench')->map(fn (array $i) => ['shirt' => $i['shirt'], 'name' => $i['name'], 'captain' => $i['captain']])->all(),
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
                'highlight' => in_array($item->entry->getKey(), [$game->home_entry_id, $game->away_entry_id], true),
            ], $entries),
        ];
    }
}
