<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\AssignGameOfficials;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\OfficialRole;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Matches\Models\Official;
use App\Modules\Matches\Scheduling\OfficialConflicts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Escala de árbitros e mesários de uma fase: cada jogo com uma pessoa por função, avisos de conflito (a mesma pessoa em
 * jogos muito próximos) e aviso por e-mail ao escalar. Gravar é `AssignGameOfficials`.
 */
class OfficialAssignmentController extends Controller
{
    public function show(Stage $stage): Response
    {
        $stage->load('division.season.competition');

        $games = Game::query()
            ->where('stage_id', $stage->getKey())
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group', 'officials'])
            ->orderBy('scheduled_at')->orderBy('round')->orderBy('number')
            ->get();

        $times = $games->pluck('scheduled_at')->filter();
        $rows = [];

        if ($times->isNotEmpty()) {
            $window = GameOfficial::query()
                ->whereHas('game', fn ($query) => $query
                    ->whereBetween('scheduled_at', [$times->min()->copy()->subDay(), $times->max()->copy()->addDay()])
                    ->whereNotIn('status', [GameStatus::Cancelled->value]))
                ->with(['official', 'game.homeEntry.team', 'game.awayEntry.team'])
                ->get();

            foreach ($window as $assignment) {
                $game = $assignment->game;
                $rows[] = [
                    'official' => $assignment->official_id,
                    'name' => $assignment->official->name,
                    'game' => $game->getKey(),
                    'at' => $game->scheduled_at->getTimestamp(),
                    'label' => ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir').' ('.$game->scheduled_at->format('d/m H:i').')',
                ];
            }
        }

        $conflicts = OfficialConflicts::find($rows, $games->pluck('id')->all());

        return Inertia::render('Panel/Stages/Officials', [
            'competition' => $stage->division->season->competition->only(['id', 'name']),
            'season' => $stage->division->season->only(['id', 'name']),
            'division' => $stage->division->only(['id', 'name']),
            'stage' => $stage->only(['id', 'name']),
            'roles' => array_map(fn (OfficialRole $role) => ['value' => $role->value, 'label' => $role->label()], OfficialRole::cases()),
            'officials' => Official::query()->orderBy('name')->get(['id', 'name', 'active'])->map(fn (Official $official) => $official->only(['id', 'name', 'active']))->values(),
            'games' => $games->map(fn (Game $game) => [
                'id' => $game->getKey(),
                'number' => $game->number,
                'round' => $game->round,
                'group' => $game->group?->name,
                'scheduled_at' => $game->scheduled_at?->format('Y-m-d\TH:i'),
                'when' => $game->scheduled_at === null ? null : $game->scheduled_at->format('d/m/Y').' às '.$game->scheduled_at->format('H:i'),
                'home' => $game->homeEntry?->team->name,
                'away' => $game->awayEntry?->team->name,
                'venue' => $game->venue_id === null ? null : $game->venue->name,
                'locked' => in_array($game->status, [GameStatus::Finished, GameStatus::Walkover, GameStatus::Cancelled], true),
                'status_label' => $game->status->label(),
                'assignments' => $game->officials->mapWithKeys(fn (GameOfficial $row) => [$row->role->value => $row->official_id])->all(),
                'conflicts' => $conflicts[$game->getKey()] ?? [],
            ])->values(),
        ]);
    }

    public function update(Request $request, Game $game, AssignGameOfficials $assign): RedirectResponse
    {
        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*' => ['nullable', 'string', 'size:26'],
        ]);

        try {
            $added = $assign->handle($game, $data['assignments']);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $added > 0 ? 'Escala salva. Quem entrou recebe um aviso por e-mail.' : 'Escala salva.');
    }
}
