<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Regras da inscrição online de uma edição (prazo, instruções, mínimo/máximo de atletas, documentos). */
class SeasonSettingsController extends Controller
{
    public function update(Request $request, Season $season): RedirectResponse
    {
        $validated = $request->validate([
            'deadline' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'min_athletes' => ['required', 'integer', 'between:1,50'],
            'max_athletes' => ['required', 'integer', 'between:1,80', 'gte:min_athletes'],
            'require_documents' => ['required', 'boolean'],
            'roster_changes' => ['sometimes', 'boolean'],
            'roster_changes_until' => ['nullable', 'date'],
        ], [], ['deadline' => 'prazo', 'roster_changes_until' => 'prazo de alterações no elenco', 'instructions' => 'instruções', 'min_athletes' => 'mínimo de atletas', 'max_athletes' => 'máximo de atletas']);

        $season->update(['registration' => [
            'deadline' => $validated['deadline'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'min_athletes' => (int) $validated['min_athletes'],
            'max_athletes' => (int) $validated['max_athletes'],
            'require_documents' => (bool) $validated['require_documents'],
            'roster_changes' => (bool) ($validated['roster_changes'] ?? true),
            'roster_changes_until' => $validated['roster_changes_until'] ?? null,
        ]]);

        return back()->with('success', 'Regras da inscrição online salvas.');
    }
}
