<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\BuildGameSheet;
use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\RecordShootout;
use App\Modules\Matches\Actions\ResetShotClock;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Actions\SyncSheetCommands;
use App\Modules\Matches\Actions\VoidGameEvent;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Http\Requests\GameEventRequest;
use App\Modules\Matches\Http\Requests\GameLineupRequest;
use App\Modules\Matches\Http\Requests\GamePeriodRequest;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Support\PublicGameSnapshot;
use App\Modules\Matches\Support\TeamTimeouts;
use App\Modules\Rules\Tactics\Formations;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SheetController extends Controller
{
    public function show(Game $game, BuildGameSheet $buildSheet, CalculateDiscipline $discipline): Response
    {
        $game->load(['division.season.competition', 'stage', 'homeEntry.team', 'awayEntry.team', 'lineups', 'tactics']);

        $state = $buildSheet->handle($game);
        $open = ! $game->status->isDecided();
        $snapshot = PublicGameSnapshot::for($game);
        $currentPeriod = $game->sport()->profile()->scoreModel === 'sets'
            ? max(1, (int) ($snapshot['timeouts']['period'] ?? 1))
            : max(1, (int) ($snapshot['clock']['period'] ?? 1));

        $team = function (?Entry $entry) use ($game, $state, $discipline, $currentPeriod): ?array {
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
                'logo' => $entry->team->logoUrl(128),
                'roster' => $roster->map($person)->all(),
                'lineup' => [
                    'starters' => $ids(LineupSlot::Starter),
                    'bench' => $ids(LineupSlot::Bench),
                    'staff' => $ids(LineupSlot::Staff),
                ],
                'has_lineup' => $lineup->isNotEmpty(),
                'tactic' => (function () use ($game, $entry): array {
                    $tactic = $game->tactics->firstWhere('entry_id', $entry->getKey());
                    $formation = $tactic?->formation !== null ? Formations::find($game->sport()->profile()->key, $tactic->formation) : null;

                    return ['formation' => $formation?->label, 'confirmed' => $tactic?->isConfirmed() ?? false];
                })(),
                'has_events' => $game->events()->effective()->where('entry_id', $entry->getKey())->exists(),
                'on_field' => $state->onField($entry->getKey()),
                'available_bench' => $state->bench($entry->getKey()),
                'expelled' => $state->expelled($entry->getKey()),
                'fouled_out' => $state->fouledOut($entry->getKey()),
                'player_fouls' => (object) $state->foulsOf($entry->getKey()),
                'yellows' => (object) $state->yellowsOf($entry->getKey()),
                'timeouts' => $game->sport()->profile()->tracksTimeouts()
                    ? ['used' => TeamTimeouts::used($game, $entry->getKey(), $currentPeriod), 'limit' => $game->sport()->profile()->timeoutLimit($currentPeriod)]
                    : null,
                'substitutions_used' => $state->substitutionsUsed($entry->getKey()),
                'substitutions_period_used' => $game->sport()->profile()->scoreModel === 'sets' ? $state->substitutionsUsedInPeriod($entry->getKey(), $currentPeriod) : null,
                'windows_used' => $state->windowsUsed($entry->getKey()),
            ];
        };

        $people = $game->lineups->pluck('person_id');
        $names = RosterEntry::query()->with('person:id,name,nickname')->whereIn('person_id', $people)
            ->whereIn('entry_id', array_filter([$game->home_entry_id, $game->away_entry_id]))->get()
            ->mapWithKeys(fn (RosterEntry $row) => [$row->person_id => $row->person->nickname ?: $row->person->name]);

        $effectiveIds = $game->events()->effective()->pluck('id')->all();
        $lastMarkId = $game->events()->effective()
            ->whereIn('type', GameEventType::clockMarks())
            ->orderByDesc('id')->value('id');

        $lastPointId = $game->events()->effective()->where('type', GameEventType::Point)->orderByDesc('id')->value('id');
        $lastShotClockId = $game->events()->effective()->where('type', GameEventType::ShotClockReset)->orderByDesc('id')->value('id');

        // A disputa de pênaltis aparece como placar (home/away_penalties), não como uma linha por cobrança.
        $events = $game->events()->effective()->where('type', '!=', GameEventType::PenaltyKick)->orderBy('id')->get()->map(fn (GameEvent $event) => [
            'id' => $event->id,
            'type' => $event->type->value,
            'entry_id' => $event->entry_id,
            'person_id' => $event->person_id,
            'person' => $event->person_id ? ($names[$event->person_id] ?? null) : null,
            'secondary_person' => $event->secondary_person_id ? ($names[$event->secondary_person_id] ?? null) : null,
            'period' => $event->period,
            'minute' => $event->minute,
            'seconds' => $event->type === GameEventType::ShotClockReset ? (int) ($event->payload['seconds'] ?? 24) : null,
            'quick' => ($event->payload['quick'] ?? false) === true,
            'voidable' => $open && in_array($event->id, $effectiveIds, true) && ($event->payload['quick'] ?? false) !== true
                && (! $event->type->isClockMark() || $event->id === $lastMarkId)
                && ($event->type !== GameEventType::Point || $event->id === $lastPointId)
                && ($event->type !== GameEventType::ShotClockReset || $event->id === $lastShotClockId),
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
                'knockout' => $game->stage->type === StageType::Knockout,
                'home_penalties' => $game->home_penalties,
                'away_penalties' => $game->away_penalties,
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
            'clock' => $snapshot['clock'] + ['server_time_ms' => (int) (microtime(true) * 1000), 'max_period' => $game->clock()->maxPeriod()],
            'shot_clock' => $snapshot['shot_clock'],
            'public_url' => route('public.games.show', $game->id),
            // Para a projeção local da fila offline (relógio e regras que o navegador reaplica enquanto não há conexão).
            'clock_offsets' => array_map(fn (int $period) => $game->clock()->offsetSeconds($period), range(1, max(1, $game->clock()->maxPeriod()))),
            'limits' => [
                'reentry' => $rules->allowSubstitutionReentry,
                'max_player_fouls' => $rules->maxPlayerFouls,
                'starters' => $rules->maxStarters,
                'bench' => $rules->maxBench,
                'staff' => $rules->maxStaff,
                'substitutions' => $rules->maxSubstitutions,
                'substitutions_per_period' => $rules->maxSubstitutionsPerPeriod,
                'windows' => $rules->maxSubstitutionWindows,
            ],
            'sport' => $game->sport()->value,
            'score_model' => $game->sport()->profile()->scoreModel,
            'sets' => $game->sport()->profile()->scoreModel === 'sets' ? ($game->sets ?? []) : null,
            'sets_to_win' => $rules->setsToWin,
            'fouls' => $snapshot['fouls'],
            'quarters' => $snapshot['quarters'],
            'event_types' => collect(match ($game->sport()->profile()->scoreModel) {
                'sets' => [GameEventType::Substitution, GameEventType::YellowCard, GameEventType::RedCard, ...($game->sport()->profile()->tracksTimeouts() ? [GameEventType::Timeout] : [])],
                'points' => [GameEventType::FieldGoal, GameEventType::ThreePointer, GameEventType::FreeThrow, GameEventType::Foul, GameEventType::TechnicalFoul, GameEventType::UnsportsmanlikeFoul, GameEventType::Substitution],
                default => [
                    GameEventType::Goal, GameEventType::OwnGoal, GameEventType::YellowCard, GameEventType::RedCard,
                    GameEventType::BlueCard, GameEventType::Substitution,
                    ...($game->sport()->profile()->tracksTeamFouls() ? [GameEventType::Foul] : []),
                ],
            })->map(fn (GameEventType $type) => ['value' => $type->value, 'label' => $type->label()])->values(),
        ]);
    }

    /**
     * Fila offline do mesário: aplica em ordem os lançamentos guardados no aparelho (ver `SyncSheetCommands`).
     * Responde JSON com o resultado de cada item; 423 se outro lote do mesmo jogo ainda está sendo aplicado.
     */
    public function sync(Request $request, Game $game, SyncSheetCommands $sync): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.key' => ['required', 'string', 'max:64'],
            'items.*.kind' => ['required', Rule::in(SyncSheetCommands::KINDS)],
            'items.*.at' => ['required', 'integer', 'min:0'],
            'items.*.payload' => ['present', 'array'],
        ]);

        try {
            /** @var list<array{key: string, kind: string, at: int, payload: array<string, mixed>}> $items */
            $items = array_values($data['items']);

            return response()->json(['results' => $sync->handle($game, $items, $request->user())]);
        } catch (LockTimeoutException) {
            return response()->json(['busy' => true, 'results' => []], 423);
        }
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
                $request->filled('person_id') ? $request->string('person_id')->toString() : null,
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

    /** Ponto de rally (esportes de sets): `side` = home|away. O set da vez é calculado pelo servidor. */
    public function point(Request $request, Game $game, RecordGameEvent $record): RedirectResponse
    {
        $validated = $request->validate(['side' => ['required', 'in:home,away']]);
        $entryId = $validated['side'] === 'home' ? $game->home_entry_id : $game->away_entry_id;

        if ($entryId === null) {
            return back()->with('error', 'O jogo ainda não tem os dois confrontantes definidos.');
        }

        try {
            $record->handle($game, GameEventType::Point, $entryId, null, null, 1, null, $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back();
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

    public function period(GamePeriodRequest $request, Game $game, ChangeGamePeriod $change): RedirectResponse
    {
        try {
            $change->handle($game, $request->string('action')->toString() === 'start', $request->integer('period'), $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Cronômetro atualizado.');
    }

    public function clockPause(Request $request, Game $game, ChangeGamePeriod $change): RedirectResponse
    {
        $validated = $request->validate(['pause' => ['required', 'boolean']]);

        try {
            $change->pause($game, (bool) $validated['pause'], $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back();
    }

    public function shotClock(Request $request, Game $game, ResetShotClock $reset): RedirectResponse
    {
        $validated = $request->validate(['seconds' => ['required', 'integer', Rule::in([14, 24])]]);

        try {
            $reset->handle($game, (int) $validated['seconds'], $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back();
    }

    public function shootout(Request $request, Game $game, RecordShootout $record): RedirectResponse
    {
        $validated = $request->validate([
            'home_penalties' => ['nullable', 'integer', 'between:0,30', 'required_with:away_penalties'],
            'away_penalties' => ['nullable', 'integer', 'between:0,30', 'required_with:home_penalties'],
        ]);

        try {
            $record->handle($game, $validated['home_penalties'] ?? null, $validated['away_penalties'] ?? null, $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pênaltis registrados.');
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
