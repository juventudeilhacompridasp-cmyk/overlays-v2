<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\ChangeTeamLogo;
use App\Modules\Teams\Http\Requests\TeamRequest;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $teams = Team::query()
            ->withCount('entries')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Team $team) => [
                ...$team->only(['id', 'name', 'short_name', 'city', 'primary_color', 'entries_count']),
                'logo_url' => $team->logoUrl(128),
            ]);

        return Inertia::render('Panel/Teams/Index', [
            'teams' => $teams,
            'filters' => ['q' => $search],
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

        return to_route('panel.teams.index')->with('success', 'Equipe atualizada.');
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
