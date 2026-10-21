<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Http\Requests\VenueRequest;
use App\Modules\Matches\Models\Venue;
use App\Support\Tables\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['name', 'city', 'address', 'games'], 'name', ['city', 'games']);

        $query = Venue::query()->withCount('games');
        $table->search($query, ['name', 'city', 'address']);
        $table->filter($query, [
            'city' => fn ($builder, string $value) => $builder->where('city', $value),
            'games' => fn ($builder, string $value) => match ($value) {
                'with' => $builder->has('games'),
                'without' => $builder->doesntHave('games'),
                default => $builder,
            },
        ]);
        $table->sort($query, [
            'name' => ['column' => 'name', 'text' => true],
            'city' => ['column' => 'city', 'text' => true, 'nullable' => true],
            'address' => ['column' => 'address', 'text' => true, 'nullable' => true],
            'games' => 'games_count',
        ]);

        return Inertia::render('Panel/Venues/Index', [
            'venues' => $query->paginate($table->perPage)->withQueryString(),
            'filters' => $table->props(),
            'city_options' => Venue::query()->whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->limit(200)->pluck('city'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Panel/Venues/Form', ['venue' => null]);
    }

    public function store(VenueRequest $request): RedirectResponse
    {
        Venue::query()->create($request->validated());

        return to_route('panel.venues.index')->with('success', 'Estádio cadastrado.');
    }

    public function edit(Venue $venue): Response
    {
        return Inertia::render('Panel/Venues/Form', ['venue' => $venue->only(['id', 'name', 'city', 'address'])]);
    }

    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        $venue->update($request->validated());

        return to_route('panel.venues.index')->with('success', 'Estádio atualizado.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $venue->delete();

        return to_route('panel.venues.index')->with('success', 'Estádio excluído.');
    }
}
