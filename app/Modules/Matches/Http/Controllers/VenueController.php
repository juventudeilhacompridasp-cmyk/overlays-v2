<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Http\Requests\VenueRequest;
use App\Modules\Matches\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $venues = Venue::query()
            ->withCount('games')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Panel/Venues/Index', [
            'venues' => $venues,
            'filters' => ['q' => $search],
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
