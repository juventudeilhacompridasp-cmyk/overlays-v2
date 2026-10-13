<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Http\Requests\CompetitionRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $competitions = Competition::query()
            ->withCount('seasons')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Panel/Competitions/Index', [
            'competitions' => $competitions,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/Competitions/Form', ['competition' => null]);
    }

    public function store(CompetitionRequest $request): RedirectResponse
    {
        $max = CurrentOrganization::get()?->limit('max_competitions');

        if ($max !== null && Competition::query()->count() >= $max) {
            return back()->with('error', "O pacote da organização permite no máximo {$max} competições.");
        }

        $competition = Competition::query()->create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($request->string('name')->toString()),
            'sport' => 'football',
        ]);

        return to_route('panel.competitions.show', $competition)->with('success', 'Competição criada. Agora cadastre a primeira edição.');
    }

    public function show(Competition $competition): Response
    {
        $seasons = $competition->seasons()
            ->withCount('divisions')
            ->orderByDesc('year')
            ->get()
            ->map(fn ($season) => [
                'id' => $season->id,
                'name' => $season->name,
                'year' => $season->year,
                'status' => $season->status->value,
                'status_label' => $season->status->label(),
                'divisions_count' => $season->divisions_count,
            ]);

        return Inertia::render('Panel/Competitions/Show', [
            'competition' => $competition->only(['id', 'name', 'description']),
            'seasons' => $seasons,
        ]);
    }

    public function edit(Competition $competition): Response
    {
        return Inertia::render('Panel/Competitions/Form', ['competition' => $competition->only(['id', 'name', 'description'])]);
    }

    public function update(CompetitionRequest $request, Competition $competition): RedirectResponse
    {
        $competition->update($request->validated());

        return to_route('panel.competitions.show', $competition)->with('success', 'Competição atualizada.');
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        $competition->delete();

        return to_route('panel.competitions.index')->with('success', 'Competição excluída, com suas edições, divisões, fases e jogos.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'competicao';
        $slug = $base;
        $suffix = 2;

        while (Competition::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
