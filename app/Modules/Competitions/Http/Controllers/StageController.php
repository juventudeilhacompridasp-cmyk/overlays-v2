<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Http\Requests\StageRequest;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Venue;
use App\Modules\Matches\Scheduling\KnockoutBracket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StageController extends Controller
{
    public function store(StageRequest $request, Division $division): RedirectResponse
    {
        $division->stages()->create($request->validated());

        return back()->with('success', 'Fase cadastrada.');
    }

    public function show(Stage $stage, CalculateStandings $standings): Response
    {
        $stage->load('division.season.competition');

        $games = Game::query()
            ->where('stage_id', $stage->getKey())
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group', 'sheetClosure'])
            ->orderBy('round')
            ->orderBy('number')
            ->get();

        $knockoutRounds = (int) $games->max('round');

        $payloadStanding = fn (array $entries) => array_map(fn (StandingEntry $item) => [
            'position' => $item->row->position,
            'team' => $item->entry->team->name,
            'logo' => $item->entry->team->logoUrl(128),
            'points' => $item->row->points,
            'played' => $item->row->played,
            'wins' => $item->row->wins,
            'draws' => $item->row->draws,
            'losses' => $item->row->losses,
            'goals_for' => $item->row->goalsFor,
            'goals_against' => $item->row->goalsAgainst,
            'goal_difference' => $item->row->goalDifference(),
            'yellow_cards' => $item->row->yellowCards,
            'red_cards' => $item->row->redCards,
            'tied' => $item->row->tied,
        ], $entries);

        $tables = match ($stage->type) {
            StageType::League => [['id' => null, 'name' => $stage->name, 'rows' => $payloadStanding($standings->forStage($stage))]],
            StageType::Groups => $stage->groups()->orderBy('sequence')->get()->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'rows' => $payloadStanding($standings->forGroup($group)),
            ])->all(),
            StageType::Knockout => [],
        };

        return Inertia::render('Panel/Stages/Show', [
            'competition' => $stage->division->season->competition->only(['id', 'name']),
            'season' => $stage->division->season->only(['id', 'name']),
            'division' => $stage->division->only(['id', 'name']),
            'stage' => [
                'id' => $stage->id,
                'name' => $stage->name,
                'type' => $stage->type->value,
                'type_label' => $stage->type->label(),
            ],
            'tables' => $tables,
            'games' => $games->map(fn (Game $game) => [
                'id' => $game->id,
                'number' => $game->number,
                'round' => $game->round,
                'group' => $game->group?->name,
                'home' => $game->homeEntry ? ['id' => $game->homeEntry->id, 'name' => $game->homeEntry->team->name, 'logo' => $game->homeEntry->team->logoUrl(128)] : null,
                'away' => $game->awayEntry ? ['id' => $game->awayEntry->id, 'name' => $game->awayEntry->team->name, 'logo' => $game->awayEntry->team->logoUrl(128)] : null,
                'home_score' => $game->home_score,
                'away_score' => $game->away_score,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'decided' => $game->status->isDecided(),
                'sheet_closed' => $game->sheetClosure !== null,
                'round_label' => $stage->type === StageType::Knockout ? KnockoutBracket::roundLabel($knockoutRounds, (int) $game->round) : null,
                'leg' => $game->leg,
                'legs' => $game->tie_id === null ? 1 : $games->where('tie_id', $game->tie_id)->count(),
                'home_penalties' => $game->home_penalties,
                'away_penalties' => $game->away_penalties,
                'scheduled_at' => $game->scheduled_at?->format('Y-m-d\TH:i'),
                'youtube_live_url' => $game->youtube_live_url,
                'venue' => $game->venue?->only(['id', 'name']),
            ])->values(),
            'venues' => Venue::query()->orderBy('name')->get(['id', 'name']),
            'generation' => [
                'can_generate' => $games->isEmpty(),
                'confirmed_entries' => Entry::query()->where('division_id', $stage->division_id)->where('status', EntryStatus::Confirmed)->count(),
                'can_clear' => $games->isNotEmpty() && ! $games->contains(fn (Game $game) => in_array($game->status, [GameStatus::Finished, GameStatus::Walkover, GameStatus::InProgress], true)),
            ],
            'statuses' => [
                ['value' => 'scheduled', 'label' => 'Agendado'],
                ['value' => 'postponed', 'label' => 'Adiado'],
                ['value' => 'suspended', 'label' => 'Suspenso'],
                ['value' => 'cancelled', 'label' => 'Cancelado'],
            ],
        ]);
    }

    public function update(StageRequest $request, Stage $stage): RedirectResponse
    {
        $stage->update($request->validated());

        return back()->with('success', 'Fase atualizada.');
    }

    public function destroy(Stage $stage): RedirectResponse
    {
        if ($stage->games()->exists()) {
            return back()->with('error', 'Esta fase já tem jogos. Exclua os jogos antes de remover a fase.');
        }

        $stage->delete();

        return back()->with('success', 'Fase excluída.');
    }
}
