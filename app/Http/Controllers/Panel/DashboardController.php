<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Support\SeasonReports;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Painel inicial: indicadores e gráficos só com AGREGADOS da organização atual (nada de dado pessoal). Os filtros (competição,
 * edição e janela de meses) valem para os jogos; os totais de competições, equipes e pessoas são sempre da organização.
 */
class DashboardController extends Controller
{
    public const MONTH_OPTIONS = [3, 6, 12];

    public function __invoke(Request $request): Response
    {
        $filters = $this->filters($request);

        $seasons = Season::query()->with('competition:id,name')
            ->when($filters['competition'], fn (Builder $query, string $competition) => $query->where('competition_id', $competition))
            ->orderByDesc('year')->orderBy('name')->get();

        return Inertia::render('Panel/Dashboard', [
            'counts' => $this->counts(),
            ...$this->data($filters),
            'filters' => $filters,
            'month_options' => self::MONTH_OPTIONS,
            'competitions' => Competition::query()->orderBy('name')->get(['id', 'name'])->map(fn (Competition $competition) => ['id' => $competition->id, 'name' => $competition->name])->values(),
            'seasons' => $seasons->map(fn (Season $season) => ['id' => $season->id, 'name' => "{$season->competition->name} · {$season->name}"])->values(),
            'export_url' => route('panel.dashboard.export', array_filter($filters, fn (mixed $value) => $value !== null)),
        ]);
    }

    /** Os mesmos números da tela (com os mesmos filtros) em CSV, para planilha; só agregados e nomes de equipes do calendário. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $data = $this->data($filters);
        $counts = $this->counts();
        $statusLabels = ['scheduled' => 'Agendados', 'in_progress' => 'Em andamento', 'finished' => 'Encerrados', 'postponed' => 'Adiados'];

        return response()->streamDownload(function () use ($data, $counts, $statusLabels): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos corretamente

            $write = fn (array $row) => fputcsv($out, array_map(fn (mixed $value) => SeasonReports::safeCell((string) $value), $row), ';');

            $write(['Indicador', 'Valor']);
            $write(['Competições', $counts['competitions']]);
            $write(['Equipes', $counts['teams']]);
            $write(['Atletas e comissão', $counts['people']]);
            $write(['Jogos encerrados (total)', $counts['games_finished']]);
            $write([]);
            $write(['Mês', 'Jogos encerrados']);

            foreach ($data['games_by_month'] as $month) {
                $write([$month['label'], $month['value']]);
            }

            $write([]);
            $write(['Situação dos jogos', 'Jogos']);

            foreach ($data['status_breakdown'] as $status => $value) {
                $write([$statusLabels[$status] ?? $status, $value]);
            }

            $write([]);
            $write(['Próximos jogos: data', 'Mandante', 'Visitante', 'Divisão', 'Situação']);

            foreach ($data['upcoming_games'] as $game) {
                $write([$game['scheduled_at'] !== null ? Carbon::parse($game['scheduled_at'])->format('d/m/Y H:i') : 'Sem horário', $game['home'], $game['away'], $game['division'] ?? '', $game['status']]);
            }

            fclose($out);
        }, 'resumo-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Filtros saneados: competição e edição precisam existir na organização (o escopo global cuida do resto); edição de outra
     * competição prevalece e corrige a competição; janela só nas opções válidas.
     *
     * @return array{competition: string|null, season: string|null, months: int}
     */
    private function filters(Request $request): array
    {
        $competition = (string) $request->query('competition', '');
        $season = (string) $request->query('season', '');
        $months = (int) $request->query('months', 6);

        $seasonModel = $season !== '' ? Season::query()->find($season) : null;
        $competitionId = $competition !== '' && Competition::query()->whereKey($competition)->exists() ? $competition : null;

        if ($seasonModel instanceof Season) {
            // A edição escolhida prevalece: a competição passa a ser a dela.
            $competitionId = (string) $seasonModel->competition_id;
        }

        return [
            'competition' => $competitionId,
            'season' => $seasonModel instanceof Season ? (string) $seasonModel->getKey() : null,
            'months' => in_array($months, self::MONTH_OPTIONS, true) ? $months : 6,
        ];
    }

    /** @return array{competitions: int, teams: int, people: int, games_finished: int} */
    private function counts(): array
    {
        return [
            'competitions' => Competition::query()->count(),
            'teams' => Team::query()->count(),
            'people' => Person::query()->count(),
            'games_finished' => Game::query()->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->count(),
        ];
    }

    /**
     * @param  array{competition: string|null, season: string|null, months: int}  $filters
     * @return Builder<Game>
     */
    private function games(array $filters): Builder
    {
        return Game::query()
            ->when($filters['season'], fn (Builder $query, string $season) => $query->whereHas('division', fn (Builder $division) => $division->where('season_id', $season)))
            ->when($filters['season'] === null && $filters['competition'] !== null, fn (Builder $query) => $query->whereHas('division.season', fn (Builder $season) => $season->where('competition_id', $filters['competition'])));
    }

    /**
     * @param  array{competition: string|null, season: string|null, months: int}  $filters
     * @return array{games_by_month: mixed, status_breakdown: mixed, upcoming_games: mixed}
     */
    private function data(array $filters): array
    {
        $now = now()->startOfMonth();
        $months = collect(range($filters['months'] - 1, 0))->map(fn (int $monthsAgo): Carbon => $now->copy()->subMonths($monthsAgo));

        $gamesByMonth = $months->map(fn (Carbon $month): array => [
            'label' => $month->format('m/Y'),
            'value' => $this->games($filters)->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
                ->whereBetween('finished_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
        ])->values();

        $statusBreakdown = collect([
            GameStatus::Scheduled,
            GameStatus::InProgress,
            GameStatus::Finished,
            GameStatus::Postponed,
        ])->mapWithKeys(fn (GameStatus $status): array => [$status->value => $this->games($filters)->where('status', $status)->count()]);

        $upcomingGames = $this->games($filters)->with(['homeEntry.team', 'awayEntry.team', 'division'])
            ->whereIn('status', [GameStatus::Scheduled, GameStatus::Postponed])
            ->whereNotNull('scheduled_at')->orderBy('scheduled_at')->limit(5)->get()
            ->map(function (Game $game): array {
                $home = $game->homeEntry?->team;
                $away = $game->awayEntry?->team;

                return [
                    'id' => $game->id,
                    'scheduled_at' => $game->scheduled_at?->toIso8601String(),
                    'status' => $game->status->label(),
                    'home' => $home ? $home->name : 'A definir',
                    'away' => $away ? $away->name : 'A definir',
                    'division' => $game->division?->name,
                ];
            })->values();

        return ['games_by_month' => $gamesByMonth, 'status_breakdown' => $statusBreakdown, 'upcoming_games' => $upcomingGames];
    }
}
