<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;

/**
 * Página pública da competição (todas as edições publicadas): histórico com campeão e vice, quadro de títulos e
 * recordes. Parametrizável por organização (`PortalSettings`). Só equipes e placares de jogos encerrados em campo;
 * nada de cartões, disciplina ou atletas. Edição em rascunho nunca entra.
 */
final class CompetitionPage
{
    private const TOP = 5;

    public function __construct(private readonly CalculateStandings $standings = new CalculateStandings) {}

    /**
     * @return array{seasons: list<array<string, mixed>>, titles: list<array<string, mixed>>, records: array<string, mixed>|null}
     */
    public function for(Competition $competition): array
    {
        $seasons = $competition->seasons()->published()->orderByDesc('year')->orderByDesc('name')->get();
        $withChampions = PortalSettings::enabled('competition_champions');
        $titles = [];

        $list = $seasons->map(function (Season $season) use ($competition, $withChampions, &$titles) {
            $divisions = [];

            if ($withChampions) {
                foreach ($season->divisions()->orderBy('name')->get() as $division) {
                    $podium = $this->podium($season, $division);

                    if ($podium['champion'] !== null) {
                        $titles[$podium['champion']['id']] ??= ['team' => $podium['champion'], 'titles' => 0, 'seasons' => []];
                        $titles[$podium['champion']['id']]['titles']++;
                        $titles[$podium['champion']['id']]['seasons'][] = $season->name;
                    }

                    $divisions[] = ['name' => $division->name, ...$podium];
                }
            }

            return [
                'id' => $season->getKey(),
                'name' => $season->name,
                'year' => $season->year,
                'status_label' => $season->status->label(),
                'url' => route('portal.season', [$competition->slug, $season->getKey()]),
                'divisions' => $divisions,
            ];
        })->values()->all();

        $ranking = collect($titles)->sortByDesc('titles')->values()->all();

        return [
            'seasons' => $list,
            'titles' => PortalSettings::enabled('competition_records') ? $ranking : [],
            'records' => PortalSettings::enabled('competition_records') ? $this->records($competition, $seasons->pluck('id')->all()) : null,
        ];
    }

    /**
     * Campeão e vice da divisão: final decidida da última fase de mata-mata; em pontos corridos, só depois de a edição
     * ser encerrada (antes disso o líder ainda não é campeão). Fase de grupos como última fase não define campeão.
     *
     * @return array{champion: array<string, mixed>|null, runner_up: array<string, mixed>|null}
     */
    public function podium(Season $season, Division $division): array
    {
        $none = ['champion' => null, 'runner_up' => null];
        $stage = $division->stages()->orderByDesc('sequence')->first();

        if ($stage === null) {
            return $none;
        }

        if ($stage->type === StageType::Knockout) {
            $bracket = BracketView::for($stage);

            return $bracket === null ? $none : ['champion' => $bracket['champion'], 'runner_up' => $bracket['runner_up']];
        }

        if ($stage->type === StageType::League && $season->status === SeasonStatus::Finished) {
            $rows = $this->standings->forStage($stage);

            return count($rows) < 1 ? $none : [
                'champion' => GamePresenter::team($rows[0]->entry),
                'runner_up' => isset($rows[1]) ? GamePresenter::team($rows[1]->entry) : null,
            ];
        }

        return $none;
    }

    /**
     * @param  list<string>  $seasonIds
     * @return array<string, mixed>
     */
    private function records(Competition $competition, array $seasonIds): array
    {
        $games = Game::query()
            ->whereHas('stage.division', fn ($division) => $division->whereIn('season_id', $seasonIds))
            ->where('status', GameStatus::Finished)
            ->with(['homeEntry.team', 'awayEntry.team', 'group', 'venue', 'stage.division.season'])
            ->get();

        [$unit, $singular] = match ($competition->sport->profile()->scoreModel) {
            'points' => ['pontos', 'ponto'],
            'sets' => ['sets', 'set'],
            default => ['gols', 'gol'],
        };

        $present = fn (Game $game) => [...GamePresenter::game($game), 'season' => $game->stage->division->season->name];
        $total = $games->sum(fn (Game $game) => $game->home_score + $game->away_score);

        return [
            'unit' => $unit,
            'unit_singular' => $singular,
            'games' => $games->count(),
            'total' => (int) $total,
            'average' => $games->isEmpty() ? 0.0 : round($total / $games->count(), 2),
            'biggest_wins' => $games->filter(fn (Game $game) => $game->home_score !== $game->away_score)
                ->sortByDesc(fn (Game $game) => [abs($game->home_score - $game->away_score), $game->home_score + $game->away_score])
                ->take(self::TOP)->map($present)->values()->all(),
            'highest_scoring' => $games->sortByDesc(fn (Game $game) => $game->home_score + $game->away_score)
                ->take(self::TOP)->map($present)->values()->all(),
        ];
    }
}
