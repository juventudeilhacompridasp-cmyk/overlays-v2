<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use Inertia\Inertia;
use Inertia\Response;

/**
 * App do mesário (PWA): os jogos de hoje, ao vivo e em aberto, em cartões grandes para o celular, com atalho
 * para a súmula e para o resultado rápido. Lançar continua sendo restrito a quem opera jogos.
 */
class MesarioController extends Controller
{
    private const LIMIT = 40;

    public function index(): Response
    {
        $games = Game::query()
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage'])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->where(fn ($query) => $query
                ->where('status', GameStatus::InProgress)
                ->orWhere(fn ($open) => $open->where('status', GameStatus::Scheduled)->whereBetween('scheduled_at', [now()->startOfDay()->subHours(3), now()->addDays(2)->endOfDay()]))
                ->orWhere(fn ($done) => $done->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->where('finished_at', '>=', now()->subHours(6))))
            ->orderByRaw("case status when 'in_progress' then 0 when 'scheduled' then 1 else 2 end")
            ->orderBy('scheduled_at')->orderBy('id')
            ->limit(self::LIMIT)->get();

        return Inertia::render('Panel/Mesario/Index', [
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
                'stage' => $game->stage->name,
                // Placar final direto (sem súmula) só faz sentido onde o placar é gols/pontos, não sets.
                'quick_result' => $game->sport()->profile()->scoreModel !== 'sets',
                'knockout' => $game->stage->type === StageType::Knockout,
            ])->values(),
        ]);
    }
}
