<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\ChangeTeamLogo;
use App\Modules\Teams\Http\Requests\TeamLogoRequest;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class TeamLogoController extends Controller
{
    public function store(TeamLogoRequest $request, Team $team, ChangeTeamLogo $change): RedirectResponse
    {
        try {
            $change->handle($team, $request->file('logo'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['logo' => $exception->getMessage()]);
        }

        return back()->with('success', 'Escudo atualizado.');
    }

    public function destroy(Team $team, ChangeTeamLogo $change): RedirectResponse
    {
        $change->handle($team, null);

        return back()->with('success', 'Escudo removido.');
    }
}
