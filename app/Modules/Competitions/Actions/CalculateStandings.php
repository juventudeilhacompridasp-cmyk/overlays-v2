<?php

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Rules\Standings\GameResult;
use App\Modules\Rules\Standings\StandingsCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Lê jogos encerrados e cartões do banco e devolve a classificação (usando o motor de regras).
 * Os participantes são as inscrições; a ordem de sorteio/cabeça de chave é o `seed` (e depois o id).
 */
final class CalculateStandings
{
    public function __construct(private readonly StandingsCalculator $calculator = new StandingsCalculator) {}

    /**
     * Classificação de um grupo.
     *
     * @return list<StandingEntry>
     */
    public function forGroup(Group $group): array
    {
        $stage = $group->stage;
        $entries = $group->entries()->with('team')->get();

        return $this->compute($stage, $entries, Game::query()->where('group_id', $group->getKey()));
    }

    /**
     * Classificação de uma fase de pontos corridos (todas as inscrições confirmadas da divisão).
     *
     * @return list<StandingEntry>
     */
    public function forStage(Stage $stage): array
    {
        if ($stage->type !== StageType::League) {
            throw new LogicException('Só fases de pontos corridos têm classificação única; use forGroup() para fases de grupos.');
        }

        $entries = Entry::query()
            ->where('division_id', $stage->division_id)
            ->where('status', EntryStatus::Confirmed)
            ->with('team')
            ->get();

        return $this->compute($stage, $entries, Game::query()->where('stage_id', $stage->getKey())->whereNull('group_id'));
    }

    /**
     * @param  Collection<int, Entry>  $entries
     * @param  Builder<Game>  $gamesQuery
     * @return list<StandingEntry>
     */
    private function compute(Stage $stage, Collection $entries, Builder $gamesQuery): array
    {
        $entries = $entries
            ->sortBy([fn (Entry $a, Entry $b) => ($a->seed ?? PHP_INT_MAX) <=> ($b->seed ?? PHP_INT_MAX), ['id', 'asc']])
            ->values();

        $games = $gamesQuery
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->whereNotNull('home_entry_id')
            ->whereNotNull('away_entry_id')
            ->get();

        $results = $games->map(fn (Game $game) => new GameResult(
            homeId: $game->home_entry_id,
            awayId: $game->away_entry_id,
            homeGoals: $game->home_score,
            awayGoals: $game->away_score,
            walkover: $game->status === GameStatus::Walkover,
            homePoints: (int) collect($game->sets ?? [])->sum('home'),
            awayPoints: (int) collect($game->sets ?? [])->sum('away'),
        ))->all();

        $rows = $this->calculator->calculate(
            teamIds: $entries->map(fn (Entry $entry) => (string) $entry->getKey())->all(),
            games: $results,
            rules: $stage->division->ruleSet(),
            cards: $this->cardsByEntry($games),
        );

        $byId = $entries->keyBy(fn (Entry $entry) => (string) $entry->getKey());

        return array_map(fn ($row) => new StandingEntry($row, $byId[$row->teamId]), $rows);
    }

    /**
     * Cartões válidos por inscrição nos jogos considerados (W.O. não conta cartões).
     *
     * @param  Collection<int, Game>  $games
     * @return array<string, array{yellow: int, red: int, blue: int}>
     */
    private function cardsByEntry(Collection $games): array
    {
        $gameIds = $games->where('status', GameStatus::Finished)->pluck('id');

        if ($gameIds->isEmpty()) {
            return [];
        }

        $cards = [];

        GameEvent::query()
            ->effective()
            ->whereIn('game_id', $gameIds)
            ->whereNotNull('entry_id')
            ->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard, GameEventType::BlueCard])
            ->get(['entry_id', 'type'])
            ->each(function (GameEvent $event) use (&$cards): void {
                $key = match ($event->type) {
                    GameEventType::YellowCard => 'yellow',
                    GameEventType::RedCard => 'red',
                    default => 'blue',
                };

                $cards[$event->entry_id] ??= ['yellow' => 0, 'red' => 0, 'blue' => 0];
                $cards[$event->entry_id][$key]++;
            });

        return $cards;
    }
}
