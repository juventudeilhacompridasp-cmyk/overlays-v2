<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\ClearStageFixtures;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Http\Requests\FixtureRequest;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class FixtureController extends Controller
{
    public function store(FixtureRequest $request, Stage $stage, GenerateStageFixtures $generate): RedirectResponse
    {
        try {
            $created = $generate->handle(
                $stage,
                $request->boolean('double_round'),
                $request->filled('groups') ? $request->integer('groups') : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Tabela gerada com {$created} jogos.");
    }

    public function destroy(Stage $stage, ClearStageFixtures $clear): RedirectResponse
    {
        try {
            $removed = $clear->handle($stage);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Tabela limpa ({$removed} jogos removidos).");
    }
}
