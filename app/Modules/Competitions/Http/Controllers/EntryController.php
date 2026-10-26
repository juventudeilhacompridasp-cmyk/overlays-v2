<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Http\Requests\EntryRequest;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    public function store(EntryRequest $request, Division $division): RedirectResponse
    {
        $division->entries()->create($request->validated());

        return back()->with('success', 'Equipe inscrita.');
    }

    public function show(Entry $entry): Response
    {
        $entry->load('team', 'division.season.competition');

        $roster = $entry->rosterEntries()->with('person')->get()
            ->sortBy(fn ($item) => [array_search($item->role, RosterRole::cases(), true), $item->status === RosterStatus::Active ? 0 : 1, $item->shirt_number ?? 999, $item->person->name])
            ->values()
            ->map(fn ($item) => [
                'id' => $item->id,
                'role' => $item->role->value,
                'role_label' => $item->role->label(),
                'shirt_number' => $item->shirt_number,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'person' => [
                    'id' => $item->person->id,
                    'name' => $item->person->name,
                    'nickname' => $item->person->nickname,
                    'age' => $item->person->birth_date?->age,
                ],
            ]);

        $people = Person::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'nickname'])
            ->map(fn ($person) => ['id' => $person->id, 'name' => $person->nickname ? "{$person->name} ({$person->nickname})" : $person->name]);

        return Inertia::render('Panel/Entries/Show', [
            'competition' => $entry->division->season->competition->only(['id', 'name']),
            'season' => $entry->division->season->only(['id', 'name']),
            'division' => $entry->division->only(['id', 'name']),
            'entry' => ['id' => $entry->id, 'status_label' => $entry->status->label()],
            'team' => $entry->team->only(['id', 'name', 'city']),
            'roster' => $roster,
            'people' => $people,
            'options' => [
                'roles' => array_map(fn (RosterRole $role) => ['value' => $role->value, 'label' => $role->label()], RosterRole::cases()),
                'statuses' => array_map(fn (RosterStatus $status) => ['value' => $status->value, 'label' => $status->label()], RosterStatus::cases()),
            ],
        ]);
    }

    public function update(EntryRequest $request, Entry $entry): RedirectResponse
    {
        $entry->update($request->safe()->only(['seed', 'status', 'club_key', 'sponsor_key']));

        return back()->with('success', 'Inscrição atualizada.');
    }

    public function destroy(Entry $entry): RedirectResponse
    {
        $hasGames = Game::query()
            ->where(fn ($query) => $query->where('home_entry_id', $entry->getKey())->orWhere('away_entry_id', $entry->getKey()))
            ->exists();

        if ($hasGames) {
            return back()->with('error', 'Esta equipe já tem jogos na divisão. Marque a inscrição como "Desistente" em vez de removê-la.');
        }

        $entry->delete();

        return back()->with('success', 'Inscrição removida.');
    }
}
