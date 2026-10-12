<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Panel/Dashboard', [
            'counts' => [
                'competitions' => Competition::query()->count(),
                'teams' => Team::query()->count(),
                'people' => Person::query()->count(),
                'games_finished' => Game::query()->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->count(),
            ],
        ]);
    }
}
