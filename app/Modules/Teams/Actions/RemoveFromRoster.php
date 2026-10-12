<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;

/**
 * Tira uma pessoa do elenco. Se ela já aparece em eventos de jogo da equipe (gols, cartões, substituições),
 * o vínculo é mantido como "removido" (preserva o histórico); senão, é apagado.
 *
 * @return bool true se apagou; false se apenas marcou como removido.
 */
final class RemoveFromRoster
{
    public function handle(RosterEntry $rosterEntry): bool
    {
        $hasHistory = GameEvent::query()
            ->where('entry_id', $rosterEntry->entry_id)
            ->where(fn ($query) => $query
                ->where('person_id', $rosterEntry->person_id)
                ->orWhere('secondary_person_id', $rosterEntry->person_id))
            ->exists();

        if ($hasHistory) {
            $rosterEntry->update(['status' => RosterStatus::Removed]);

            return false;
        }

        $rosterEntry->delete();

        return true;
    }
}
