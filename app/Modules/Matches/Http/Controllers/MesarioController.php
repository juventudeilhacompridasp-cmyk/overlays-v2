<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\Support\MesarioSettings;
use App\Modules\Tenancy\Support\OperationSettings;
use App\Support\Tables\TableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * App do mesário (PWA): os jogos de hoje, ao vivo e em aberto, em cartões grandes para o celular, com atalho
 * para a súmula e para o resultado rápido. Lançar continua sendo restrito a quem opera jogos.
 */
class MesarioController extends Controller
{
    public function index(): Response
    {
        $quickResult = OperationSettings::quickResultAllowed();
        $window = MesarioSettings::all();
        $games = Game::query()
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage'])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->where(fn ($query) => $query
                ->where('status', GameStatus::InProgress)
                ->orWhere(fn ($open) => $open->where('status', GameStatus::Scheduled)->whereBetween('scheduled_at', [now()->startOfDay()->subHours(3), now()->addDays($window['days_ahead'])->endOfDay()]))
                ->orWhere(fn ($done) => $done->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->where('finished_at', '>=', now()->subHours($window['finished_hours']))))
            ->orderByRaw("case status when 'in_progress' then 0 when 'scheduled' then 1 else 2 end")
            ->orderBy('scheduled_at')->orderBy('id')
            ->limit($window['limit'])->get();

        return Inertia::render('Panel/Mesario/Index', [
            'user_id' => auth()->id(),
            'games' => $games->map(fn (Game $game) => [
                'id' => $game->getKey(),
                'home' => $game->homeEntry->team->name,
                'away' => $game->awayEntry->team->name,
                'home_score' => $game->home_score,
                'away_score' => $game->away_score,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'open' => ! $game->status->isDecided(),
                'live' => $game->status === GameStatus::InProgress,
                'scheduled_at' => $game->scheduled_at?->format('d/m H:i'),
                'venue' => $game->venue?->name,
                'competition' => "{$game->division->season->competition->name} · {$game->division->name}",
                'sport' => $game->sport()->profile()->summary(),
                'stage' => $game->stage->name,
                // Placar final direto (sem súmula) só faz sentido onde o placar é gols/pontos, não sets.
                'quick_result' => $quickResult && $game->division->ruleSet()->allowQuickResult && $game->sport()->profile()->scoreModel !== 'sets',
                'knockout' => $game->stage->type === StageType::Knockout,
            ])->values(),
        ]);
    }

    /**
     * Jogos do campeonato (todas as edições da organização) para o mesário consultar e abrir o painel do operador: lista
     * paginada no banco com busca e filtros por situação e competição. Só dados do jogo; nada pessoal.
     */
    public function games(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['when', 'status'], 'when', ['status', 'competition'], 'desc', 25);
        $query = Game::query()
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage'])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id');

        $table->search($query, ['(select lower(t.name) from entries e join teams t on t.id = e.team_id where e.id = games.home_entry_id)', '(select lower(t.name) from entries e join teams t on t.id = e.team_id where e.id = games.away_entry_id)']);
        $table->filter($query, [
            'status' => fn ($builder, string $value) => GameStatus::tryFrom($value) !== null ? $builder->where('status', $value) : $builder,
            'competition' => fn ($builder, string $value) => $builder->whereHas('division.season', fn ($season) => $season->where('competition_id', $value)),
        ]);
        $table->sort($query, [
            'when' => ['column' => 'scheduled_at', 'nullable' => true],
            'status' => 'status',
        ]);

        $games = $query->paginate($table->perPage)->withQueryString()->through(fn (Game $game) => [
            'id' => $game->getKey(),
            'home' => $game->homeEntry->team->name,
            'away' => $game->awayEntry->team->name,
            'home_score' => $game->home_score,
            'away_score' => $game->away_score,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'live' => $game->status === GameStatus::InProgress,
            'open' => ! $game->status->isDecided(),
            'scheduled_at' => $game->scheduled_at?->toIso8601String(),
            'venue' => $game->venue?->name,
            'competition' => "{$game->division->season->competition->name} · {$game->division->name}",
            'sport' => $game->sport()->profile()->summary(),
            'stage' => $game->stage->name,
        ]);

        return Inertia::render('Panel/Mesario/Games', [
            'games' => $games,
            'filters' => $table->props(),
            'statuses' => collect(GameStatus::cases())->map(fn (GameStatus $status) => ['value' => $status->value, 'label' => $status->label()])->values(),
            'competitions' => Competition::query()->orderBy('name')->get(['id', 'name'])->map(fn (Competition $competition) => ['value' => $competition->getKey(), 'label' => $competition->name])->values(),
        ]);
    }
}
