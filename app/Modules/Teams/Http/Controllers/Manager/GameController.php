<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Actions\SubmitTeamLineup;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Matches\Support\ManagerLineupWindow;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Rules\Tactics\Formations;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Http\Requests\ManagerLineupRequest;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Support\ManagedTeams;
use App\Modules\Teams\Support\ManagerGameRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Jogos das equipes do gestor: lista (com filtro por situação), detalhe e o editor de escalação e esquema tático. */
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
            ->paginate(20)->withQueryString();

        $mine = fn (Game $game): Entry => $entries->get($game->home_entry_id) ?? $entries->get($game->away_entry_id) ?? abort(404);
        $status = ManagerGameRow::lineupStatuses($games->getCollection(), $mine);

        $games->through(fn (Game $game) => [...ManagerGameRow::of($game, $mine($game)), 'lineup' => $status[$game->getKey()]]);

        return Inertia::render('Manager/Games/Index', ['games' => $games, 'filter' => $filter]);
    }

    public function show(Request $request, Game $game, CalculateDiscipline $discipline): Response
    {
        $game->load(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition', 'stage']);

        $mine = ManagedTeams::entryInGame($request->user(), $game);
        $mine->loadMissing(['team', 'division']);
        $opponent = $game->home_entry_id === $mine->getKey() ? $game->awayEntry : $game->homeEntry;

        $sport = $game->sport()->profile()->key;
        $rules = $game->division->ruleSet();
        $suspended = $discipline->forEntry($mine)->suspended();

        $roster = RosterEntry::query()
            ->where('entry_id', $mine->getKey())
            ->where('status', RosterStatus::Active)
            ->with('person')
            ->get()
            ->sortBy(fn (RosterEntry $row) => [$row->role === RosterRole::Athlete ? 0 : 1, $row->shirt_number ?? 999, $row->person->name])
            ->values();

        $rows = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $mine->getKey())->orderBy('sort')->get();
        $ids = fn (LineupSlot $slot): array => $rows->where('slot', $slot)->pluck('person_id')->values()->all();
        $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $mine->getKey())->first();

        return Inertia::render('Manager/Games/Show', [
            'game' => [...ManagerGameRow::of($game, $mine), 'lineup' => ManagerGameRow::lineupStatuses(collect([$game]), fn () => $mine)[$game->getKey()]],
            'sport' => $sport,
            'formations' => array_map(fn ($formation) => $formation->toArray(), Formations::for($sport)),
            'limits' => ['starters' => $rules->maxStarters, 'bench' => $rules->maxBench, 'staff' => $rules->maxStaff],
            'roster' => $roster->map(fn (RosterEntry $row) => [
                'id' => $row->person_id,
                'name' => $row->person->nickname ?: $row->person->name,
                'shirt_number' => $row->shirt_number,
                'is_athlete' => $row->role === RosterRole::Athlete,
                'role_label' => $row->role->label(),
                'suspended_games' => $suspended[$row->person_id] ?? 0,
            ])->all(),
            'lineup' => [
                'starters' => $ids(LineupSlot::Starter),
                'bench' => $ids(LineupSlot::Bench),
                'staff' => $ids(LineupSlot::Staff),
                'positions' => $rows->whereNotNull('position')->pluck('position', 'person_id')->all(),
                'captain' => $rows->firstWhere('is_captain', true)?->person_id,
            ],
            'tactic' => [
                'formation' => $tactic?->formation,
                'notes' => $tactic?->notes,
                'confirmed_at' => $tactic?->confirmed_at?->format('d/m/Y H:i'),
            ],
            'window' => [
                'closed_reason' => ManagerLineupWindow::closedReason($game),
                'deadline' => ManagerLineupWindow::deadline($game)?->format('d/m/Y H:i'),
            ],
            'previous' => $this->previous($game, $mine),
            'opponent' => $opponent !== null && $game->status !== GameStatus::Scheduled ? $this->opponent($game, $opponent, $sport) : null,
        ]);
    }

    public function lineup(ManagerLineupRequest $request, Game $game, SubmitTeamLineup $submit): RedirectResponse
    {
        $game->load(['homeEntry', 'awayEntry', 'division']);

        $mine = ManagedTeams::entryInGame($request->user(), $game);
        $confirm = $request->boolean('confirm');

        try {
            $submit->handle(
                $game,
                $mine,
                $request->user(),
                $request->string('formation')->toString(),
                array_values($request->input('starters', [])),
                array_values($request->input('bench', [])),
                array_values($request->input('staff', [])),
                (array) $request->input('positions', []),
                $request->filled('captain') ? $request->string('captain')->toString() : null,
                $request->input('notes'),
                $confirm,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $confirm ? 'Escalação confirmada.' : 'Rascunho da escalação salvo.');
    }

    /**
     * A última escalação da mesma inscrição em outro jogo, para repetir com um clique (o front descarta quem não pode mais).
     *
     * @return array<string, mixed>|null
     */
    private function previous(Game $game, Entry $mine): ?array
    {
        $last = GameTactic::query()->where('entry_id', $mine->getKey())->where('game_id', '!=', $game->getKey())->latest('updated_at')->first();

        if ($last === null) {
            return null;
        }

        $rows = GameLineup::query()->where('game_id', $last->game_id)->where('entry_id', $mine->getKey())->orderBy('sort')->get();
        $ids = fn (LineupSlot $slot): array => $rows->where('slot', $slot)->pluck('person_id')->values()->all();

        return [
            'formation' => $last->formation,
            'starters' => $ids(LineupSlot::Starter),
            'bench' => $ids(LineupSlot::Bench),
            'staff' => $ids(LineupSlot::Staff),
            'positions' => $rows->whereNotNull('position')->pluck('position', 'person_id')->all(),
            'captain' => $rows->firstWhere('is_captain', true)?->person_id,
        ];
    }

    /**
     * Escalação do adversário, só depois que o jogo começa e só com nomes públicos (menor = primeiro nome e inicial).
     *
     * @return array<string, mixed>|null
     */
    private function opponent(Game $game, Entry $opponent, string $sport): ?array
    {
        $rows = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $opponent->getKey())
            ->whereIn('slot', [LineupSlot::Starter, LineupSlot::Bench])->with('person')->orderBy('sort')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $opponent->getKey())->first();
        $formation = $tactic?->formation !== null ? Formations::find($sport, $tactic->formation) : null;

        $line = fn (GameLineup $row): array => [
            'shirt' => $row->shirt_number,
            'name' => PublicName::of($row->person),
            'position' => $formation?->slot((string) $row->position)?->abbr,
            'captain' => $row->is_captain,
        ];

        return [
            'team' => $opponent->team->name,
            'formation' => $formation?->label,
            'starters' => $rows->where('slot', LineupSlot::Starter)->map($line)->values()->all(),
            'bench' => $rows->where('slot', LineupSlot::Bench)->map($line)->values()->all(),
        ];
    }
}
