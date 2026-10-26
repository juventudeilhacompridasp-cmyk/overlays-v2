<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Actions\ChangeTeamLogo;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Http\Requests\TeamRequest;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Support\Tables\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['name', 'city', 'entries'], 'name', ['city', 'entries']);

        $query = Team::query()->withCount('entries');
        $table->search($query, ['name', 'short_name', 'city']);
        $table->filter($query, [
            'city' => fn ($builder, string $value) => $builder->where('city', $value),
            'entries' => fn ($builder, string $value) => match ($value) {
                'with' => $builder->has('entries'),
                'without' => $builder->doesntHave('entries'),
                default => $builder,
            },
        ]);
        $table->sort($query, [
            'name' => ['column' => 'name', 'text' => true],
            'city' => ['column' => 'city', 'text' => true, 'nullable' => true],
            'entries' => 'entries_count',
        ]);

        $teams = $query->paginate($table->perPage)
            ->withQueryString()
            ->through(fn (Team $team) => [
                ...$team->only(['id', 'name', 'short_name', 'city', 'primary_color', 'entries_count']),
                'logo_url' => $team->logoUrl(128),
            ]);

        return Inertia::render('Panel/Teams/Index', [
            'teams' => $teams,
            'filters' => $table->props(),
            'city_options' => Team::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->limit(200)->pluck('city'),
        ]);
    }

    /** Ficha da equipe: dados, inscrições, elenco (de todas as edições) e jogos. Consultar vale para qualquer papel; editar é `manage-registry`. */
    public function show(Team $team): Response
    {
        $entries = $team->entries()->with('division.season.competition')->get()
            ->sortByDesc(fn ($entry) => $entry->division->season->year ?? 0)->values();
        $entryIds = $entries->pluck('id')->all();
        $labels = $entries->mapWithKeys(fn ($entry) => [$entry->id => "{$entry->division->season->competition->name} · {$entry->division->season->name} · {$entry->division->name}"]);

        $roster = RosterEntry::query()->whereIn('entry_id', $entryIds)->with('person')->get()
            ->sortBy(fn (RosterEntry $item) => [array_search($item->role, RosterRole::cases(), true), $item->status === RosterStatus::Active ? 0 : 1, $item->shirt_number ?? 999, $item->person->name])
            ->values()
            ->map(fn (RosterEntry $item) => [
                'id' => $item->getKey(),
                'entry' => $labels[$item->entry_id] ?? '—',
                'role' => $item->role->label(),
                'shirt_number' => $item->shirt_number,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'person' => [
                    'id' => $item->person->getKey(),
                    'name' => $item->person->name,
                    'nickname' => $item->person->nickname,
                    'age' => $item->person->birth_date?->age,
                    'photo_url' => $item->person->photoUrl(96),
                ],
            ]);

        $games = Game::query()
            ->where(fn ($query) => $query->whereIn('home_entry_id', $entryIds)->orWhereIn('away_entry_id', $entryIds))
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition'])
            ->get();

        $mine = fn (Game $game): bool => in_array($game->home_entry_id, $entryIds, true);
        $row = fn (Game $game): array => [
            'id' => $game->getKey(),
            'home' => $mine($game),
            'opponent' => ($mine($game) ? $game->awayEntry : $game->homeEntry)?->team->name,
            'my_score' => $mine($game) ? $game->home_score : $game->away_score,
            'opponent_score' => $mine($game) ? $game->away_score : $game->home_score,
            'decided' => $game->status->isDecided(),
            'status_label' => $game->status->label(),
            'competition' => $game->division->season->competition->name,
            'scheduled_at' => $game->scheduled_at?->format('d/m/Y H:i'),
            'venue' => $game->venue?->name,
        ];

        $decided = $games->filter(fn (Game $game) => $game->status->isDecided());
        $record = ['played' => $decided->count(), 'wins' => 0, 'draws' => 0, 'losses' => 0];
        foreach ($decided as $game) {
            $own = $mine($game) ? $game->home_score : $game->away_score;
            $other = $mine($game) ? $game->away_score : $game->home_score;
            $own > $other ? $record['wins']++ : ($own < $other ? $record['losses']++ : $record['draws']++);
        }

        return Inertia::render('Panel/Teams/Show', [
            'team' => [...$team->only(['id', 'name', 'short_name', 'city', 'primary_color']), 'logo_url' => $team->logoUrl(512)],
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->getKey(),
                'label' => $labels[$entry->getKey()],
                'competition' => $entry->division->season->competition->name,
                'season' => $entry->division->season->name,
                'division' => $entry->division->name,
                'status_label' => $entry->status->label(),
                'athletes' => $roster->where('status', RosterStatus::Active->value)->where('role', RosterRole::Athlete->label())->where('entry', $labels[$entry->getKey()])->count(),
            ])->values(),
            'roster' => $roster,
            'record' => $record,
            'upcoming' => $games->filter(fn (Game $game) => ! $game->status->isDecided() && $game->status !== GameStatus::Cancelled)
                ->sortBy(fn (Game $game) => $game->scheduled_at->timestamp ?? PHP_INT_MAX)->take(5)->map($row)->values(),
            'recent' => $decided->sortByDesc(fn (Game $game) => $game->finished_at->timestamp ?? $game->scheduled_at->timestamp ?? 0)->take(5)->map($row)->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/Teams/Form', ['team' => null, 'managers' => null]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        Team::query()->create($request->validated());

        return to_route('panel.teams.index')->with('success', 'Equipe cadastrada.');
    }

    public function edit(Team $team): Response
    {
        $managers = CurrentOrganization::has(Feature::TeamPortal)
            ? TeamManager::query()->where('team_id', $team->getKey())->with('user:id,name,email')->get()
                ->map(fn (TeamManager $link) => ['id' => $link->getKey(), 'name' => $link->user->name, 'email' => $link->user->email])->values()
            : null;

        return Inertia::render('Panel/Teams/Form', [
            'team' => [...$team->only(['id', 'name', 'short_name', 'city', 'primary_color']), 'logo_url' => $team->logoUrl(512)],
            // null = o pacote da organização não inclui a área do gestor (a tela esconde a seção).
            'managers' => $managers,
        ]);
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return to_route('panel.teams.show', $team)->with('success', 'Equipe atualizada.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        if ($team->entries()->exists()) {
            return back()->with('error', 'Esta equipe está inscrita em campeonatos. Remova as inscrições antes de excluí-la.');
        }

        app(ChangeTeamLogo::class)->handle($team, null);
        $team->delete();

        return to_route('panel.teams.index')->with('success', 'Equipe excluída.');
    }
}
