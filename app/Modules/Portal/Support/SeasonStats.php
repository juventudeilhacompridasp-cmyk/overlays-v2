<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use Illuminate\Support\Collection;

/**
 * Estatísticas públicas de uma edição, por divisão: ataque e defesa, forma recente, maiores vitórias e jogos
 * mais "movimentados". Só jogos encerrados em campo (W.O. fica de fora). Dados de equipe e placar: nada de
 * cartões, disciplina ou atletas. A unidade do placar (gols, pontos, sets) vem do esporte.
 */
final class SeasonStats
{
    private const TOP = 5;

    private const FORM = 5;

    /** @return array{unit: string, unit_singular: string, divisions: list<array<string, mixed>>} */
    public function handle(Season $season): array
    {
        $season->loadMissing('competition');
        [$unit, $singular] = match ($season->competition->sport->profile()->scoreModel) {
            'points' => ['pontos', 'ponto'],
            'sets' => ['sets', 'set'],
            default => ['gols', 'gol'],
        };

        return [
            'unit' => $unit,
            'unit_singular' => $singular,
            'divisions' => $season->divisions()->orderBy('name')->get()->map(fn (Division $division) => $this->division($division))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function division(Division $division): array
    {
        /** @var Collection<int, Game> $games */
        $games = Game::query()
            ->where('division_id', $division->getKey())
            ->where('status', GameStatus::Finished)
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group'])
            ->orderBy('finished_at')->orderBy('id')
            ->get();

        $teams = [];

        foreach ($games as $game) {
            foreach ([[$game->homeEntry, $game->home_score, $game->away_score], [$game->awayEntry, $game->away_score, $game->home_score]] as [$entry, $for, $against]) {
                $id = (string) $entry->getKey();
                $teams[$id] ??= ['team' => GamePresenter::team($entry), 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'for' => 0, 'against' => 0, 'clean_sheets' => 0, 'form' => []];
                $teams[$id]['played']++;
                $teams[$id]['for'] += $for;
                $teams[$id]['against'] += $against;
                $teams[$id]['clean_sheets'] += $against === 0 ? 1 : 0;
                $result = $for > $against ? 'V' : ($for === $against ? 'E' : 'D');
                $teams[$id][['V' => 'wins', 'E' => 'draws', 'D' => 'losses'][$result]]++;
                $teams[$id]['form'][] = $result;
            }
        }

        $rows = collect($teams)->map(fn (array $row) => [
            ...$row,
            'avg_for' => round($row['for'] / $row['played'], 2),
            'avg_against' => round($row['against'] / $row['played'], 2),
            'win_rate' => round($row['wins'] / $row['played'] * 100, 1),
            'form' => array_slice($row['form'], -self::FORM),
        ])->values();

        $total = $games->sum(fn (Game $game) => $game->home_score + $game->away_score);
        $count = $games->count();

        return [
            'id' => $division->getKey(),
            'name' => $division->name,
            'summary' => [
                'games' => $count,
                'total' => $total,
                'average' => $count > 0 ? round($total / $count, 2) : 0,
                'home_wins' => $games->filter(fn (Game $game) => $game->home_score > $game->away_score)->count(),
                'draws' => $games->filter(fn (Game $game) => $game->home_score === $game->away_score)->count(),
                'away_wins' => $games->filter(fn (Game $game) => $game->home_score < $game->away_score)->count(),
            ],
            'best_attack' => $rows->sortByDesc(fn (array $row) => [$row['avg_for'], $row['for']])->first(),
            'best_defense' => $rows->sortBy(fn (array $row) => [$row['avg_against'], -$row['clean_sheets']])->first(),
            'teams' => $rows->sortByDesc(fn (array $row) => [$row['wins'], $row['for'] - $row['against']])->values()->all(),
            'biggest_wins' => $games->filter(fn (Game $game) => $game->home_score !== $game->away_score)
                ->sortByDesc(fn (Game $game) => [abs($game->home_score - $game->away_score), $game->home_score + $game->away_score])
                ->take(self::TOP)->map(fn (Game $game) => GamePresenter::game($game))->values()->all(),
            'highest_scoring' => $games->sortByDesc(fn (Game $game) => $game->home_score + $game->away_score)
                ->take(self::TOP)->map(fn (Game $game) => GamePresenter::game($game))->values()->all(),
        ];
    }
}
