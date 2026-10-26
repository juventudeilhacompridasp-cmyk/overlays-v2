<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Venue;
use App\Modules\Matches\Scheduling\GameConflicts;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agenda de uma fase: os jogos por dia, para remarcar arrastando, com aviso de conflito (mesmo estádio ou mesma equipe em
 * horários próximos, inclusive com jogos de outras fases e divisões). Gravar continua sendo `GameController::update`.
 */
class ScheduleController extends Controller
{
    /** Situações em que o jogo ainda pode ser remarcado (o resto é travado na tela). */
    private const MOVABLE = [GameStatus::Scheduled, GameStatus::Postponed, GameStatus::Suspended];

    public function show(Stage $stage): Response
    {
        $stage->load('division.season.competition');
        $rules = $stage->division->ruleSet();

        $games = Game::query()
            ->where('stage_id', $stage->getKey())
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'group'])
            ->orderBy('round')->orderBy('number')
            ->get();

        $times = $games->pluck('scheduled_at')->filter();
        $conflicts = [];

        if ($times->isNotEmpty()) {
            $from = $times->min()->copy()->subDay();
            $to = $times->max()->copy()->addDay();

            $window = Game::query()
                ->whereBetween('scheduled_at', [$from, $to])
                ->whereNotIn('status', [GameStatus::Cancelled->value])
                ->with(['homeEntry.team', 'awayEntry.team'])
                ->get();

            $conflicts = GameConflicts::find(
                $window->map(fn (Game $game) => [
                    'id' => $game->getKey(),
                    'at' => $game->scheduled_at->getTimestamp(),
                    'venue' => $game->venue_id,
                    'teams' => array_values(array_filter([$game->home_entry_id, $game->away_entry_id])),
                    'label' => ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir').' ('.$game->scheduled_at->format('d/m H:i').')',
                ])->all(),
                $games->pluck('id')->all(),
                $rules->venueConflictMinutes,
                $rules->teamRestMinutes,
            );
        }

        return Inertia::render('Panel/Stages/Schedule', [
            'competition' => $stage->division->season->competition->only(['id', 'name']),
            'season' => $stage->division->season->only(['id', 'name']),
            'division' => $stage->division->only(['id', 'name']),
            'stage' => ['id' => $stage->getKey(), 'name' => $stage->name],
            'games' => $games->map(fn (Game $game) => [
                'id' => $game->getKey(),
                'number' => $game->number,
                'round' => $game->round,
                'group' => $game->group?->name,
                'home' => $game->homeEntry?->team->name,
                'away' => $game->awayEntry?->team->name,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'movable' => in_array($game->status, self::MOVABLE, true),
                'scheduled_at' => $game->scheduled_at?->format('Y-m-d\TH:i'),
                'venue_id' => $game->venue_id,
                'venue' => $game->venue?->name,
                'conflicts' => $conflicts[$game->getKey()] ?? [],
            ])->values(),
            'venues' => Venue::query()->orderBy('name')->get(['id', 'name']),
            'rules' => ['venue_minutes' => $rules->venueConflictMinutes, 'team_rest_minutes' => $rules->teamRestMinutes],
        ]);
    }
}
