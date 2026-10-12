<?php

namespace App\Modules\Competitions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Http\Requests\StageRequest;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Stage;
use Illuminate\Http\RedirectResponse;

class StageController extends Controller
{
    public function store(StageRequest $request, Division $division): RedirectResponse
    {
        $division->stages()->create($request->validated());

        return back()->with('success', 'Fase cadastrada.');
    }

    public function update(StageRequest $request, Stage $stage): RedirectResponse
    {
        $stage->update($request->validated());

        return back()->with('success', 'Fase atualizada.');
    }

    public function destroy(Stage $stage): RedirectResponse
    {
        if ($stage->games()->exists()) {
            return back()->with('error', 'Esta fase já tem jogos. Exclua os jogos antes de remover a fase.');
        }

        $stage->delete();

        return back()->with('success', 'Fase excluída.');
    }
}
