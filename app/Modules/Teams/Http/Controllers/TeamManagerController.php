<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\InviteTeamManager;
use App\Modules\Teams\Actions\RemoveTeamManager;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Painel (manage-registry): convidar, reenviar o convite e remover gestores de uma equipe (ADR 0003). */
class TeamManagerController extends Controller
{
    public function store(Request $request, Team $team, InviteTeamManager $invite): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [], ['name' => 'nome', 'email' => 'e-mail']);

        try {
            $invite->handle($team, $data['name'], $data['email'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Gestor convidado. Enviamos o e-mail com o acesso.');
    }

    public function resend(TeamManager $teamManager, InviteTeamManager $invite): RedirectResponse
    {
        $invite->resend($teamManager);

        return back()->with('success', 'Convite reenviado.');
    }

    public function destroy(TeamManager $teamManager, RemoveTeamManager $remove): RedirectResponse
    {
        $remove->handle($teamManager);

        return back()->with('success', 'Gestor removido da equipe.');
    }
}
