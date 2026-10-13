<?php

namespace App\Modules\Teams\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Registration\Models\Registration;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterChange;
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

        $supplements = Registration::query()->where('entry_id', $entry->getKey())->where('kind', Registration::KIND_SUPPLEMENT)
            ->withCount('people')->orderByDesc('created_at')->limit(10)->get();

        $changes = RosterChange::query()->where('entry_id', $entry->getKey())->with('user:id,name')->orderByDesc('created_at')->orderByDesc('id')->limit(15)->get();

        return Inertia::render('Manager/Entry', [
            'window' => ['closed_reason' => $entry->division->season->rosterChangesClosedReason()],
            'max_athletes' => $entry->division->season->registrationSettings()['max_athletes'],
            'supplements' => $supplements->map(fn (Registration $registration) => [
                'id' => $registration->getKey(),
                'status' => $registration->status->value,
                'status_label' => $registration->status->label(),
                'people_count' => $registration->people_count,
                'review_note' => $registration->review_note,
                'created_at' => $registration->created_at?->format('d/m/Y'),
                'url' => $registration->status->isEditable() ? $registration->manageUrl() : null,
            ])->values(),
            'changes' => $changes->map(fn (RosterChange $change) => [
                'id' => $change->getKey(),
                'text' => self::describe($change),
                'by' => $change->user?->name,
                'at' => $change->created_at?->format('d/m/Y H:i'),
            ])->values(),
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

    private static function describe(RosterChange $change): string
    {
        $details = $change->details ?? [];
        $name = (string) ($details['name'] ?? '');
        $show = fn (mixed $value): string => $value === null || $value === '' ? 'sem' : (string) $value;

        return match ($change->action) {
            RosterChange::SHIRT => "Camisa de {$name}: {$show($details['from'] ?? null)} → {$show($details['to'] ?? null)}",
            RosterChange::NICKNAME => "Apelido de {$name}: {$show($details['from'] ?? null)} → {$show($details['to'] ?? null)}",
            RosterChange::RELEASED => "{$name} desligado(a) do elenco",
            RosterChange::SUPPLEMENT_REQUESTED => 'Pedido de reforço aberto',
            RosterChange::SUPPLEMENT_APPROVED => 'Reforços aprovados e incluídos no elenco ('.(int) ($details['people'] ?? 0).')',
            default => $change->action,
        };
    }
}
