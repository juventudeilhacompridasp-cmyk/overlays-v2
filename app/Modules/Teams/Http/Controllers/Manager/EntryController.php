<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Support\ManagedTeams;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Elenco e situação disciplinar da inscrição do gestor numa edição. Mostra dados da PRÓPRIA equipe (nome, apelido,
 * camisa, idade, foto por URL assinada, cartões e suspensões); nunca CPF nem documentos (ADR 0003, LGPD).
 */
class EntryController extends Controller
{
    public function show(Request $request, Entry $entry, CalculateDiscipline $discipline): Response
    {
        ManagedTeams::authorizeEntry($request->user(), $entry);

        $entry->load(['team', 'division.season.competition']);

        $tracker = $discipline->forEntry($entry);
        $suspended = $tracker->suspended();

        $cards = GameEvent::query()->effective()
            ->where('entry_id', $entry->getKey())
            ->whereNotNull('person_id')
            ->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard])
            ->get(['person_id', 'type'])
            ->groupBy('person_id');

        $roster = RosterEntry::query()
            ->where('entry_id', $entry->getKey())
            ->where('status', RosterStatus::Active)
            ->with('person')
            ->get()
            ->sortBy(fn (RosterEntry $row) => [$row->role === RosterRole::Athlete ? 0 : 1, $row->shirt_number ?? 999, $row->person->name])
            ->values();

        return Inertia::render('Manager/Entry', [
            'entry' => [
                'id' => $entry->getKey(),
                'team' => $entry->team->name,
                'logo_url' => $entry->team->logoUrl(128),
                'competition' => $entry->division->season->competition->name,
                'season' => $entry->division->season->name,
                'division' => $entry->division->name,
            ],
            'roster' => $roster->map(function (RosterEntry $row) use ($cards, $suspended, $tracker) {
                $events = $cards->get($row->person_id, collect());

                return [
                    'id' => $row->getKey(),
                    'name' => $row->person->name,
                    'nickname' => $row->person->nickname,
                    'shirt_number' => $row->shirt_number,
                    'role' => $row->role->value,
                    'role_label' => $row->role->label(),
                    'age' => $row->person->birth_date?->age,
                    'photo_url' => $row->person->photoUrl(96),
                    'yellow' => $events->where('type', GameEventType::YellowCard)->count(),
                    'red' => $events->where('type', GameEventType::RedCard)->count(),
                    'suspended_games' => $suspended[$row->person_id] ?? 0,
                    'series_yellows' => $tracker->seriesYellows($row->person_id),
                ];
            })->all(),
        ]);
    }
}
