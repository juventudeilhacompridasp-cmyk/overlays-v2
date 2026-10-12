<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Http\Requests\EntryRequest;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;
use Illuminate\Http\RedirectResponse;

class EntryController extends Controller
{
    public function store(EntryRequest $request, Division $division): RedirectResponse
    {
        $division->entries()->create($request->validated());

        return back()->with('success', 'Equipe inscrita.');
    }

    public function update(EntryRequest $request, Entry $entry): RedirectResponse
    {
        $entry->update($request->safe()->only(['seed', 'status']));

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
