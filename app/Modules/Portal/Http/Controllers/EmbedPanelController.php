<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use Inertia\Inertia;
use Inertia\Response;

/** Painel: gerador do código dos widgets (iframe) para colar em outros sites. */
class EmbedPanelController extends Controller
{
    public function index(): Response
    {
        $seasons = Season::query()->with(['competition', 'divisions' => fn ($query) => $query->orderBy('name')])->orderByDesc('year')->orderBy('name')->limit(60)->get();

        $games = Game::query()
            ->whereIn('status', [GameStatus::InProgress, GameStatus::Scheduled])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->with(['homeEntry.team', 'awayEntry.team', 'division.season'])
            ->orderByRaw("case status when 'in_progress' then 0 else 1 end")->orderBy('scheduled_at')
            ->limit(40)->get();

        return Inertia::render('Panel/Embeds/Index', [
            'base_url' => url('/embed'),
            'seasons' => $seasons->map(fn (Season $season) => [
                'id' => $season->getKey(),
                'name' => "{$season->competition->name} · {$season->name}",
                'published' => $season->isPublished(),
                'divisions' => $season->divisions->map(fn ($division) => ['id' => $division->getKey(), 'name' => $division->name])->values(),
            ])->values(),
            'games' => $games->map(fn (Game $game) => [
                'id' => $game->getKey(),
                'name' => ($game->homeEntry->team->name ?? '?').' x '.($game->awayEntry->team->name ?? '?'),
                'live' => $game->status === GameStatus::InProgress,
                'scheduled_at' => $game->scheduled_at?->format('d/m H:i'),
                'published' => $game->division->season->isPublished(),
            ])->values(),
        ]);
    }
}
