<?php

namespace App\Modules\Arts\Support;

use App\Modules\Arts\Enums\ArtKind;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Rules\Tactics\Formations;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Support\Images\ImageStore;

/**
 * Dados de uma partida para as artes (ADR 0009): só o que o portal público já mostra, mais a escalação do jogo. Nomes de
 * atletas SEMPRE por `PublicName`; nunca cartões, disciplina, documentos, fotos nem nascimento. Escudos e logotipo viajam
 * como data URI (o canvas do navegador não exporta imagem de outra origem).
 */
final class ArtData
{
    /** Máximo de jogos numa arte de rodada (a partir daí a arte fica ilegível). */
    public const MAX_GAMES = 8;

    /** @return array<string, mixed> */
    public static function for(Game $game, ArtKind $kind, string $scope = 'day'): array
    {
        $game->loadMissing(['division.season.competition', 'stage', 'group', 'venue', 'homeEntry.team', 'awayEntry.team']);
        $organization = CurrentOrganization::get();

        return [
            'kind' => $kind->value,
            'game_id' => $game->getKey(),
            'competition' => $game->division->season->competition->name,
            'season' => $game->division->season->name,
            'division' => $game->division->name,
            'phase' => $game->stage->name,
            'group' => $game->group?->name,
            'round' => $game->round,
            'leg' => $game->leg,
            'date' => $game->scheduled_at?->format('Y-m-d\TH:i'),
            'venue' => $game->venue?->name,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'decided' => $game->status->isDecided(),
            'unit' => $game->sport()->profile()->scoreModel === 'sets' ? 'sets' : null,
            'home' => self::side($game->homeEntry, $game->home_score, $game->home_penalties),
            'away' => self::side($game->awayEntry, $game->away_score, $game->away_penalties),
            'org' => [
                'name' => $organization?->name,
                'logo' => $organization !== null ? ImageStore::publicDataUri($organization->logo_path, 128) : null,
            ],
            'lineup' => $kind === ArtKind::Lineup ? self::lineup($game) : null,
            'scope' => $scope === 'round' ? 'round' : 'day',
            'games' => $kind === ArtKind::Round ? self::games($game, $scope === 'round') : [],
        ];
    }

    /**
     * Jogos da arte de rodada: os do mesmo dia (padrão) ou os da mesma rodada da fase, na mesma edição, até {@see MAX_GAMES}.
     *
     * @return list<array<string, mixed>>
     */
    private static function games(Game $game, bool $byRound): array
    {
        $query = Game::query()->with(['homeEntry.team', 'awayEntry.team', 'venue'])
            ->whereHas('division', fn ($division) => $division->where('season_id', $game->division->season_id));

        if ($byRound && $game->round !== null) {
            $query->where('stage_id', $game->stage_id)->where('round', $game->round);
        } elseif ($game->scheduled_at !== null) {
            $query->whereDate('scheduled_at', $game->scheduled_at->toDateString());
        } else {
            $query->whereKey($game->getKey());
        }

        return $query->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->orderBy('number')->limit(self::MAX_GAMES)->get()
            ->map(fn (Game $item) => [
                'id' => $item->getKey(),
                'date' => $item->scheduled_at?->format('Y-m-d\TH:i'),
                'venue' => $item->venue?->name,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'decided' => $item->status->isDecided(),
                'home' => self::side($item->homeEntry, $item->home_score, $item->home_penalties),
                'away' => self::side($item->awayEntry, $item->away_score, $item->away_penalties),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    private static function side(?Entry $entry, ?int $score, ?int $penalties): array
    {
        $team = $entry?->team;

        return [
            'name' => $team->name ?? 'A definir',
            'short' => $team->short_name ?? null,
            'crest' => $team !== null ? ImageStore::publicDataUri($team->logo_path, 128) : null,
            'color' => $team->primary_color ?? null,
            'score' => $score,
            'penalties' => $penalties,
        ];
    }

    /** @return array{home: array<string, mixed>, away: array<string, mixed>}|null null = nenhuma escalação lançada */
    private static function lineup(Game $game): ?array
    {
        if ($game->homeEntry === null || $game->awayEntry === null) {
            return null;
        }

        $home = self::lineupSide($game, $game->homeEntry);
        $away = self::lineupSide($game, $game->awayEntry);

        return $home['starters'] === [] && $away['starters'] === [] ? null : ['home' => $home, 'away' => $away];
    }

    /** @return array<string, mixed> */
    private static function lineupSide(Game $game, Entry $entry): array
    {
        $rows = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->get();
        $roster = RosterEntry::query()->where('entry_id', $entry->getKey())->with('person')->get()->keyBy('person_id');
        $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->first();
        $formation = $tactic?->formation !== null ? Formations::find($game->sport()->profile()->key, $tactic->formation) : null;

        $people = fn (LineupSlot $slot) => $rows->where('slot', $slot)
            ->map(function (GameLineup $row) use ($roster, $formation): array {
                $item = $roster->get($row->person_id);

                return [
                    'shirt' => $row->shirt_number,
                    'name' => PublicName::of($item?->person) ?? '—',
                    'position' => $formation?->slot((string) $row->position)?->abbr,
                    'captain' => (bool) $row->is_captain,
                    'role' => $item?->role,
                ];
            })
            ->sortBy(fn (array $person) => $person['shirt'] ?? 999)
            ->values();

        $coach = $people(LineupSlot::Staff)->first(fn (array $person) => $person['role'] === RosterRole::Coach);
        $plain = fn ($list) => $list->map(fn (array $person) => ['shirt' => $person['shirt'], 'name' => $person['name'], 'position' => $person['position'], 'captain' => $person['captain']])->all();

        return [
            'formation' => $formation?->label,
            'starters' => $plain($people(LineupSlot::Starter)),
            'bench' => $plain($people(LineupSlot::Bench)),
            'coach' => $coach['name'] ?? null,
        ];
    }
}
