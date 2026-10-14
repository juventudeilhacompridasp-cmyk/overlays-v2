<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = now()->startOfMonth();
        $months = collect(range(5, 0))->map(fn (int $monthsAgo): Carbon => $now->copy()->subMonths($monthsAgo));

        $gamesByMonth = $months->map(fn (Carbon $month): array => [
            'label' => $month->format('m/Y'),
            'value' => Game::query()->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
                ->whereBetween('finished_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
        ])->values();

        $statusBreakdown = collect([
            GameStatus::Scheduled,
            GameStatus::InProgress,
            GameStatus::Finished,
            GameStatus::Postponed,
        ])->mapWithKeys(fn (GameStatus $status): array => [$status->value => Game::query()->where('status', $status)->count()]);

        $upcomingGames = Game::query()->with(['homeEntry.team', 'awayEntry.team', 'division'])
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

        return Inertia::render('Panel/Dashboard', [
            'counts' => [
                'competitions' => Competition::query()->count(),
                'teams' => Team::query()->count(),
                'people' => Person::query()->count(),
                'games_finished' => Game::query()->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->count(),
            ],
            'games_by_month' => $gamesByMonth,
            'status_breakdown' => $statusBreakdown,
            'upcoming_games' => $upcomingGames,
        ]);
    }
}
