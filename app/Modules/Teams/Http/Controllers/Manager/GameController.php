<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Support\ManagedTeams;
use App\Modules\Teams\Support\ManagerGameRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Jogos das equipes do gestor: lista (com filtro por situação) e detalhe. */
class GameController extends Controller
{
    private const FILTERS = ['open', 'done', 'all'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = in_array($request->query('filtro'), self::FILTERS, true) ? (string) $request->query('filtro') : 'open';

        $entries = ManagedTeams::entries($user)->with('team')->get()->keyBy('id');

        $games = ManagedTeams::games($user)
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage'])
            ->when($filter === 'open', fn ($query) => $query->whereNotIn('status', [GameStatus::Finished, GameStatus::Walkover, GameStatus::Cancelled])->orderByRaw('scheduled_at is null')->orderBy('scheduled_at'))
            ->when($filter === 'done', fn ($query) => $query->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])->orderByDesc('finished_at'))
            ->when($filter === 'all', fn ($query) => $query->orderByRaw('scheduled_at is null')->orderBy('scheduled_at'))
            ->orderBy('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Game $game) => ManagerGameRow::of($game, $entries->get($game->home_entry_id) ?? $entries->get($game->away_entry_id) ?? abort(404)));

        return Inertia::render('Manager/Games/Index', ['games' => $games, 'filter' => $filter]);
    }

    public function show(Request $request, Game $game): Response
    {
        $game->load(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage']);

        $mine = ManagedTeams::entryInGame($request->user(), $game);

        return Inertia::render('Manager/Games/Show', [
            'game' => ManagerGameRow::of($game, $mine),
        ]);
    }
}
