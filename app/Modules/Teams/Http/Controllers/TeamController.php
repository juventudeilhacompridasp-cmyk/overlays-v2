<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Http\Requests\TeamRequest;
use App\Modules\Teams\Models\Team;
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
            ->withQueryString();

        return Inertia::render('Panel/Teams/Index', [
            'teams' => $teams,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/Teams/Form', ['team' => null]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        Team::query()->create($request->validated());

        return to_route('panel.teams.index')->with('success', 'Equipe cadastrada.');
    }

    public function edit(Team $team): Response
    {
        return Inertia::render('Panel/Teams/Form', ['team' => $team->only(['id', 'name', 'short_name', 'city', 'primary_color'])]);
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

        $team->delete();

        return to_route('panel.teams.index')->with('success', 'Equipe excluída.');
    }
}
