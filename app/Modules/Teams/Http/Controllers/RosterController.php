<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Actions\AddToRoster;
use App\Modules\Teams\Actions\RemoveFromRoster;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Http\Requests\RosterStoreRequest;
use App\Modules\Teams\Http\Requests\RosterUpdateRequest;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RosterController extends Controller
{
    public function store(RosterStoreRequest $request, Entry $entry, AddToRoster $add): RedirectResponse
    {
        $role = RosterRole::from($request->string('role')->toString());
        $status = $request->filled('status') ? RosterStatus::from($request->string('status')->toString()) : RosterStatus::Active;
        $shirt = $request->filled('shirt_number') ? $request->integer('shirt_number') : null;

        // Em transação: se uma regra do elenco falhar, a pessoa nova também não é criada.
        $person = DB::transaction(function () use ($request, $entry, $add, $role, $shirt, $status): Person {
            $person = $request->filled('person_id')
                ? Person::query()->findOrFail($request->string('person_id')->toString())
                : Person::query()->create($request->newPersonData());

            $add->handle($entry, $person, $role, $shirt, $status);

            return $person;
        });

        return back()->with('success', "{$person->name} entrou no elenco.");
    }

    public function update(RosterUpdateRequest $request, RosterEntry $rosterEntry, AddToRoster $rules): RedirectResponse
    {
        $role = RosterRole::from($request->string('role')->toString());
        $status = RosterStatus::from($request->string('status')->toString());
        $shirt = $role === RosterRole::Athlete && $request->filled('shirt_number') ? $request->integer('shirt_number') : null;

        $rules->ensureAllowed($rosterEntry->entry, $rosterEntry->person, $role, $shirt, $status, $rosterEntry);

        $rosterEntry->update(['role' => $role, 'shirt_number' => $shirt, 'status' => $status]);

        return back()->with('success', 'Elenco atualizado.');
    }

    public function destroy(RosterEntry $rosterEntry, RemoveFromRoster $remove): RedirectResponse
    {
        $name = $rosterEntry->person->name;

        $deleted = $remove->handle($rosterEntry);

        return back()->with('success', $deleted
            ? "{$name} saiu do elenco."
            : "{$name} tem histórico de jogos: o vínculo foi marcado como removido, sem apagar o histórico.");
    }
}
