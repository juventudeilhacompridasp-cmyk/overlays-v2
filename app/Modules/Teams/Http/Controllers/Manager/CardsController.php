<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Actions\IssueAthleteCard;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Http\Controllers\CardController;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\Request;
use Inertia\Response;

/** Carteirinhas do elenco ativo da PRÓPRIA equipe do gestor (o código de verificação é emitido sob demanda). */
class CardsController extends Controller
{
    public function entry(Request $request, Entry $entry, IssueAthleteCard $issue, CardController $cards): Response
    {
        ManagedTeams::authorizeEntry($request->user(), $entry);

        $members = $entry->rosterEntries()->where('status', RosterStatus::Active)->with('person')->get()
            ->sortBy([fn ($a, $b) => ($a->role === RosterRole::Athlete ? 0 : 1) <=> ($b->role === RosterRole::Athlete ? 0 : 1), fn ($a, $b) => ($a->shirt_number ?? 999) <=> ($b->shirt_number ?? 999), fn ($a, $b) => $a->person->name <=> $b->person->name])
            ->values()->all();

        return $cards->present($members, $entry, $issue, route('manager.entries.show', $entry->getKey()), false);
    }
}
