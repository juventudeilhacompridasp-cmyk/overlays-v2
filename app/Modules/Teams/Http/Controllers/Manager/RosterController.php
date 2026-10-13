<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\ChangeManagedRoster;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Alterações de elenco feitas pelo próprio gestor: camisa, apelido e desligamento (ADR 0003). */
class RosterController extends Controller
{
    public function update(Request $request, RosterEntry $rosterEntry, ChangeManagedRoster $change): RedirectResponse
    {
        $this->authorizeRow($request, $rosterEntry);

        $data = $request->validate([
            'shirt_number' => ['sometimes', 'nullable', 'integer', 'between:0,99'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:60'],
        ], [], ['shirt_number' => 'camisa', 'nickname' => 'apelido']);

        try {
            if (array_key_exists('shirt_number', $data)) {
                $change->changeShirt($rosterEntry, $data['shirt_number'] === null ? null : (int) $data['shirt_number'], $request->user());
            }

            if (array_key_exists('nickname', $data)) {
                $change->changeNickname($rosterEntry, $data['nickname'], $request->user());
            }
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Elenco atualizado.');
    }

    public function destroy(Request $request, RosterEntry $rosterEntry, ChangeManagedRoster $change): RedirectResponse
    {
        $this->authorizeRow($request, $rosterEntry);

        try {
            $change->release($rosterEntry, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Desligado(a) do elenco e retirado(a) das escalações dos jogos que ainda não começaram.');
    }

    private function authorizeRow(Request $request, RosterEntry $rosterEntry): void
    {
        ManagedTeams::authorizeEntry($request->user(), $rosterEntry->entry);
    }
}
