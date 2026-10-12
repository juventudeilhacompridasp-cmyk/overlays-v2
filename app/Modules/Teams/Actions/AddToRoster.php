<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Validation\ValidationException;

/**
 * Coloca uma pessoa no elenco de uma inscrição (equipe em uma divisão), com função e camisa.
 *
 * Regras:
 * - a mesma pessoa não pode ter a mesma função duas vezes no elenco;
 * - a camisa é única entre os atletas ativos do elenco;
 * - atleta só pode estar ativo em UMA equipe por divisão (comissão pode atuar em mais de uma).
 */
final class AddToRoster
{
    public function handle(Entry $entry, Person $person, RosterRole $role, ?int $shirtNumber = null, RosterStatus $status = RosterStatus::Active): RosterEntry
    {
        $this->ensureAllowed($entry, $person, $role, $shirtNumber, $status);

        return RosterEntry::query()->create([
            'organization_id' => $entry->organization_id,
            'entry_id' => $entry->getKey(),
            'person_id' => $person->getKey(),
            'role' => $role,
            'shirt_number' => $role === RosterRole::Athlete ? $shirtNumber : null,
            'status' => $status,
        ]);
    }

    /**
     * Valida as regras do elenco (também usada na edição, ignorando o próprio registro).
     */
    public function ensureAllowed(Entry $entry, Person $person, RosterRole $role, ?int $shirtNumber, RosterStatus $status, ?RosterEntry $ignoring = null): void
    {
        $others = RosterEntry::query()
            ->when($ignoring !== null, fn ($query) => $query->whereKeyNot($ignoring?->getKey()));

        $sameRole = (clone $others)
            ->where('entry_id', $entry->getKey())
            ->where('person_id', $person->getKey())
            ->where('role', $role)
            ->exists();

        if ($sameRole) {
            throw ValidationException::withMessages(['role' => "{$person->name} já está no elenco com esta função."]);
        }

        if ($role !== RosterRole::Athlete || $status !== RosterStatus::Active) {
            return;
        }

        if ($shirtNumber !== null) {
            $shirtTaken = (clone $others)
                ->where('entry_id', $entry->getKey())
                ->where('role', RosterRole::Athlete)
                ->where('status', RosterStatus::Active)
                ->where('shirt_number', $shirtNumber)
                ->exists();

            if ($shirtTaken) {
                throw ValidationException::withMessages(['shirt_number' => "Já existe um atleta com a camisa {$shirtNumber} neste elenco."]);
            }
        }

        $elsewhere = (clone $others)
            ->where('person_id', $person->getKey())
            ->where('role', RosterRole::Athlete)
            ->where('status', RosterStatus::Active)
            ->where('entry_id', '!=', $entry->getKey())
            ->whereHas('entry', fn ($query) => $query->where('division_id', $entry->division_id))
            ->with('entry.team')
            ->first();

        if ($elsewhere !== null) {
            throw ValidationException::withMessages([
                'person_id' => "{$person->name} já é atleta de {$elsewhere->entry->team->name} nesta divisão.",
            ]);
        }
    }
}
