<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\BuildGameSheet;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Http\Requests\GameEventRequest;
use App\Modules\Matches\Http\Requests\GameLineupRequest;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SheetController extends Controller
{
    public function show(Game $game, BuildGameSheet $buildSheet, CalculateDiscipline $discipline): Response
    {
        $game->load(['division.season.competition', 'stage', 'homeEntry.team', 'awayEntry.team', 'lineups']);

        $state = $buildSheet->handle($game);
        $open = ! $game->status->isDecided();

        $team = function (?Entry $entry) use ($game, $state, $discipline): ?array {
            if ($entry === null) {
                return null;
            }

            $suspended = $discipline->forEntry($entry)->suspended();
            $lineup = $game->lineups->where('entry_id', $entry->getKey());
            $ids = fn (LineupSlot $slot) => $lineup->where('slot', $slot)->pluck('person_id')->values();

            $roster = RosterEntry::query()
                ->where('entry_id', $entry->getKey())
                ->where('status', RosterStatus::Active)
                ->with('person:id,name,nickname')
                ->get()
                ->sortBy(fn (RosterEntry $row) => [$row->role === RosterRole::Athlete ? 0 : 1, $row->shirt_number ?? 999, $row->person->name])
                ->values();

            $person = fn (RosterEntry $row) => [
                'id' => $row->person_id,
                'name' => $row->person->nickname ?: $row->person->name,
                'shirt_number' => $row->shirt_number,
                'role' => $row->role->value,
                'role_label' => $row->role->label(),
                'is_athlete' => $row->role === RosterRole::Athlete,
                'suspended_games' => $suspended[$row->person_id] ?? 0,
            ];

            return [
                'id' => $entry->getKey(),
                'name' => $entry->team->name,
                'roster' => $roster->map($person)->all(),
                'lineup' => [
                    'starters' => $ids(LineupSlot::Starter),
                    'bench' => $ids(LineupSlot::Bench),
                    'staff' => $ids(LineupSlot::Staff),
                ],
                'has_lineup' => $lineup->isNotEmpty(),
                'has_events' => $game->events()->effective()->where('entry_id', $entry->getKey())->exists(),
                'on_field' => $state->onField($entry->getKey()),
                'available_bench' => $state->bench($entry->getKey()),
                'expelled' => $state->expelled($entry->getKey()),
                'substitutions_used' => $state->substitutionsUsed($entry->getKey()),
                'windows_used' => $state->windowsUsed($entry->getKey()),
            ];
        };

        $people = $game->lineups->pluck('person_id');
        $names = RosterEntry::query()->with('person:id,name,nickname')->whereIn('person_id', $people)
            ->whereIn('entry_id', array_filter([$game->home_entry_id, $game->away_entry_id]))->get()
            ->mapWithKeys(fn (RosterEntry $row) => [$row->person_id => $row->person->nickname ?: $row->person->name]);

        $effectiveIds = $game->events()->effective()->pluck('id')->all();

        $events = $game->events()->effective()->orderBy('id')->get()->map(fn (GameEvent $event) => [
            'id' => $event->id,
            'type' => $event->type->value,
            'entry_id' => $event->entry_id,
            'person' => $event->person_id ? ($names[$event->person_id] ?? null) : null,
            'secondary_person' => $event->secondary_person_id ? ($names[$event->secondary_person_id] ?? null) : null,
            'period' => $event->period,
            'minute' => $event->minute,
            'quick' => ($event->payload['quick'] ?? false) === true,
            'voidable' => $open && in_array($event->id, $effectiveIds, true) && ($event->payload['quick'] ?? false) !== true,
        ]);

        $division = $game->division;
        $rules = $division->ruleSet();

        return Inertia::render('Panel/Games/Sheet', [
            'game' => [
                'id' => $game->id,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'open' => $open,
                'home_score' => $game->home_score,
                'away_score' => $game->away_score,
                'stage_id' => $game->stage_id,
                'stage' => $game->stage->name,
                'division' => $division->name,
                'season_id' => $division->season_id,
                'season' => $division->season->name,
                'competition_id' => $division->season->competition_id,
                'competition' => $division->season->competition->name,
                'division_id' => $division->id,
            ],
            'home' => $team($game->homeEntry),
            'away' => $team($game->awayEntry),
            'events' => $events,
            'limits' => [
                'starters' => $rules->maxStarters,
                'bench' => $rules->maxBench,
                'staff' => $rules->maxStaff,
                'substitutions' => $rules->maxSubstitutions,
                'windows' => $rules->maxSubstitutionWindows,
            ],
            'event_types' => collect([
                GameEventType::Goal, GameEventType::OwnGoal, GameEventType::YellowCard, GameEventType::RedCard,
                GameEventType::BlueCard, GameEventType::Substitution,
            ])->map(fn (GameEventType $type) => ['value' => $type->value, 'label' => $type->label()])->values(),
        ]);
    }

    public function lineup(GameLineupRequest $request, Game $game, Entry $entry, SaveGameLineup $save): RedirectResponse
    {
        try {
            $save->handle($game, $entry, $request->array('starters'), $request->array('bench'), $request->array('staff'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Escalação salva.');
    }

    public function storeEvent(GameEventRequest $request, Game $game, RecordGameEvent $record): RedirectResponse
    {
        try {
            $record->handle(
                $game,
                GameEventType::from($request->string('type')->toString()),
                $request->string('entry_id')->toString(),
                $request->string('person_id')->toString(),
                $request->filled('secondary_person_id') ? $request->string('secondary_person_id')->toString() : null,
                $request->integer('period'),
                $request->filled('minute') ? $request->integer('minute') : null,
                $request->user()?->getAuthIdentifier(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Lançamento registrado.');
    }

    public function voidEvent(Game $game, GameEvent $event, VoidGameEvent $void): RedirectResponse
    {
        try {
            $void->handle($game, $event, request()->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Lançamento anulado.');
    }

    public function finish(Game $game, FinishGame $finish): RedirectResponse
    {
        try {
            $finish->handle($game);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Jogo encerrado.');
    }
}
