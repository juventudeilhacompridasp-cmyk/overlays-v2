<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Support\ManagedTeams;
use App\Modules\Teams\Support\ManagerGameRow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** Início do gestor: as equipes dele, as inscrições (edições) e os próximos jogos / últimos resultados. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $teams = ManagedTeams::teams($user)->orderBy('name')->get();

        $entries = ManagedTeams::entries($user)
            ->with(['team', 'division.season.competition'])
            ->get()
            ->sortByDesc(fn (Entry $entry) => $entry->division->season->created_at)
            ->values();

        $mine = $entries->keyBy('id');

        $withRelations = ['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage'];

        $upcoming = ManagedTeams::games($user)->with($withRelations)
            ->whereIn('status', [GameStatus::Scheduled, GameStatus::InProgress, GameStatus::Postponed])
            ->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->orderBy('id')->limit(6)->get();

        $recent = ManagedTeams::games($user)->with($withRelations)
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->orderByDesc('finished_at')->orderByDesc('id')->limit(5)->get();

        $mineIn = fn (Game $game): Entry => $this->mine($game, $mine);
        $status = ManagerGameRow::lineupStatuses($upcoming, $mineIn);
        $row = fn (Game $game): array => [...ManagerGameRow::of($game, $mineIn($game)), 'lineup' => $status[$game->getKey()] ?? null];

        return Inertia::render('Manager/Dashboard', [
            'teams' => $teams->map(fn (Team $team) => ['id' => $team->getKey(), 'name' => $team->name, 'logo_url' => $team->logoUrl(128)])->values(),
            'entries' => $entries->map(fn (Entry $entry) => [
                'id' => $entry->getKey(),
                'team' => $entry->team->name,
                'competition' => $entry->division->season->competition->name,
                'season' => $entry->division->season->name,
                'division' => $entry->division->name,
                'status' => $entry->status->value,
            ])->values(),
            'upcoming' => $upcoming->map($row)->values(),
            'recent' => $recent->map($row)->values(),
        ]);
    }

    /**
     * @param  Collection<string, Entry>  $mine
     */
    private function mine(Game $game, Collection $mine): Entry
    {
        return $mine->get($game->home_entry_id) ?? $mine->get($game->away_entry_id) ?? abort(404);
    }
}
