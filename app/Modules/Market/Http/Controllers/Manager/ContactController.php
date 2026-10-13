<?php

namespace App\Modules\Market\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Market\Actions\RequestContact;
use App\Modules\Market\Actions\WithdrawContactRequest;
use App\Modules\Market\Models\ContactRequest;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Pedidos de contato do gestor (ADR 0008, parte 3): sempre em nome de uma equipe DELE (404 fora do escopo). */
class ContactController extends Controller
{
    public function store(Request $request, MarketProfile $profile, RequestContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', Rule::in(ManagedTeams::ids($request->user()))],
            'message' => ['required', 'string', 'min:5', 'max:300'],
        ], [], ['team_id' => 'equipe', 'message' => 'mensagem']);

        $team = Team::query()->findOrFail($data['team_id']);

        try {
            $contact->handle($request->user(), $profile, $team, trim($data['message']));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pedido enviado. O jogador recebe um aviso e decide se compartilha o contato.');
    }

    public function destroy(Request $request, ContactRequest $contactRequest, WithdrawContactRequest $withdraw): RedirectResponse
    {
        ManagedTeams::authorizeTeam($request->user(), $contactRequest->team);

        try {
            $withdraw->handle($contactRequest);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pedido cancelado.');
    }
}
