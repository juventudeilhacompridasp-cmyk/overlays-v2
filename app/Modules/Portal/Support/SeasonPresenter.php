<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Scheduling\KnockoutBracket;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\Support\PortalQuantities;

/**
 * Edição inteira no formato público: divisões, fases (classificação, rodadas, mata-mata) e artilharia.
 * Só dados de competição: nada de cartões, disciplina, escalação ou documentos; nomes de atletas via {@see PublicName}.
 */
final class SeasonPresenter
{
    public function __construct(private readonly CalculateStandings $standings = new CalculateStandings) {}

    /** @return array<string, mixed> */
    public function handle(Season $season): array
    {
        $season->loadMissing('competition');

        return [
            'competition' => ['id' => $season->competition->getKey(), 'name' => $season->competition->name, 'slug' => $season->competition->slug],
            'season' => [
                'id' => $season->getKey(),
                'name' => $season->name,
                'year' => $season->year,
                'status' => $season->status->value,
                'status_label' => $season->status->label(),
                'starts_on' => $season->starts_on?->toDateString(),
                'ends_on' => $season->ends_on?->toDateString(),
            ],
            'divisions' => $season->divisions()->orderBy('name')->get()->map(fn (Division $division) => $this->division($division))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function division(Division $division): array
    {
        $games = Game::query()
            ->where('division_id', $division->getKey())
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group'])
            ->orderBy('round')->orderBy('number')->orderBy('scheduled_at')
            ->get();

        return [
            'id' => $division->getKey(),
            'name' => $division->name,
            'category' => $division->category,
            'stages' => $division->stages()->orderBy('sequence')->get()
                ->map(fn (Stage $stage) => $this->stage($stage, $games->where('stage_id', $stage->getKey())))
                ->values()->all(),
            'scorers' => $this->scorers($games),
        ];
    }

    /**
     * @param  iterable<int, Game>  $games
     * @return array<string, mixed>
     */
    private function stage(Stage $stage, iterable $games): array
    {
        $games = collect($games)->values();
        $knockout = $stage->type === StageType::Knockout;
        $totalRounds = (int) $games->max('round');

        $rounds = $games->groupBy(fn (Game $game) => (int) $game->round)->sortKeys()->map(fn ($roundGames, $round) => [
            'label' => $knockout && $round > 0 ? KnockoutBracket::roundLabel($totalRounds, (int) $round) : ($round > 0 ? "Rodada {$round}" : 'Jogos'),
            'games' => $roundGames->map(fn (Game $game) => GamePresenter::game($game))->values()->all(),
        ])->values()->all();

        return [
            'id' => $stage->getKey(),
            'name' => $stage->name,
            'type' => $stage->type->value,
            'knockout' => $knockout,
            'tables' => $this->tables($stage),
            'rounds' => $rounds,
        ];
    }

    /** @return list<array{name: string, rows: list<array<string, mixed>>}> */
    private function tables(Stage $stage): array
    {
        $row = fn (StandingEntry $item) => [
            'position' => $item->row->position,
            'team' => GamePresenter::team($item->entry),
            'points' => $item->row->points,
            'played' => $item->row->played,
            'wins' => $item->row->wins,
            'draws' => $item->row->draws,
            'losses' => $item->row->losses,
            'goals_for' => $item->row->goalsFor,
            'goals_against' => $item->row->goalsAgainst,
            'goal_difference' => $item->row->goalDifference(),
        ];

        return match ($stage->type) {
            StageType::League => [['name' => $stage->name, 'rows' => array_map($row, $this->standings->forStage($stage))]],
            StageType::Groups => $stage->groups()->orderBy('sequence')->get()->map(fn ($group) => [
                'name' => $group->name,
                'rows' => array_map($row, $this->standings->forGroup($group)),
            ])->values()->all(),
            StageType::Knockout => [],
        };
    }

    /**
     * Artilharia: gols válidos (sem gol contra) por atleta na divisão.
     *
     * @param  iterable<int, Game>  $games
     * @return list<array{name: string, team: string|null, goals: int}>
     */
    private function scorers(iterable $games): array
    {
        $games = collect($games);

        $events = GameEvent::query()->effective()
            ->where('type', GameEventType::Goal)
            ->whereNotNull('person_id')
            ->whereIn('game_id', $games->pluck('id'))
            ->get(['person_id', 'entry_id']);

        $top = $events->groupBy(fn (GameEvent $event) => $event->person_id.'|'.$event->entry_id)
            ->map(fn ($group) => ['person_id' => $group->first()->person_id, 'entry_id' => $group->first()->entry_id, 'goals' => $group->count()])
            ->sortByDesc('goals')->take(PortalQuantities::all()['scorers'])->values();

        $people = Person::query()->whereIn('id', $top->pluck('person_id'))->get()->keyBy('id');
        $teams = $games->flatMap(fn (Game $game) => [$game->homeEntry, $game->awayEntry])->filter()->unique('id')->mapWithKeys(fn ($entry) => [$entry->getKey() => $entry->team->name]);

        return $top->map(fn (array $item) => [
            'name' => PublicName::of($people->get($item['person_id'])) ?? '—',
            'team' => $teams->get($item['entry_id']),
            'goals' => $item['goals'],
        ])->all();
    }
}
