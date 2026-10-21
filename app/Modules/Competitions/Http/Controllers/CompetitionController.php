<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\Sport;
use App\Modules\Competitions\Http\Requests\CompetitionRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Tenancy\CurrentOrganization;
use App\Support\Tables\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['name', 'seasons'], 'name', ['seasons']);

        $query = Competition::query()->withCount('seasons');
        $table->search($query, ['name', 'description']);
        $table->filter($query, [
            'seasons' => fn ($builder, string $value) => match ($value) {
                'with' => $builder->has('seasons'),
                'without' => $builder->doesntHave('seasons'),
                default => $builder,
            },
        ]);
        $table->sort($query, [
            'name' => ['column' => 'name', 'text' => true],
            'seasons' => 'seasons_count',
        ]);

        return Inertia::render('Panel/Competitions/Index', [
            'competitions' => $query->paginate($table->perPage)->withQueryString(),
            'filters' => $table->props(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/Competitions/Form', ['competition' => null, 'sports' => $this->sports(), 'sport_locked' => false]);
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
            'sport' => $request->validated('sport', Sport::Football->value),
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
            'competition' => [...$competition->only(['id', 'name', 'description']), 'sport_label' => $competition->sport->label()],
            'seasons' => $seasons,
        ]);
    }

    public function edit(Competition $competition): Response
    {
        return Inertia::render('Panel/Competitions/Form', [
            'competition' => [...$competition->only(['id', 'name', 'description']), 'sport' => $competition->sport->value],
            'sports' => $this->sports(),
            // O esporte define cronômetro e regulamento padrão: só dá para trocar enquanto não há edições.
            'sport_locked' => $competition->seasons()->exists(),
        ]);
    }

    public function update(CompetitionRequest $request, Competition $competition): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['sport']) && $data['sport'] !== $competition->sport->value && $competition->seasons()->exists()) {
            return back()->with('error', 'O esporte não pode mudar depois que a competição tem edições.');
        }

        $competition->update($data);

        return to_route('panel.competitions.show', $competition)->with('success', 'Competição atualizada.');
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        $competition->delete();

        return to_route('panel.competitions.index')->with('success', 'Competição excluída, com suas edições, divisões, fases e jogos.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sports(): array
    {
        return array_map(fn (Sport $sport) => ['value' => $sport->value, 'label' => $sport->label()], Sport::cases());
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
