<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Teams\Actions\IssueAthleteCard;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Painel: carteirinhas (uma ou o elenco inteiro) com QR para verificação pública. Foto só por URL assinada. */
class CardController extends Controller
{
    public function show(RosterEntry $rosterEntry, IssueAthleteCard $issue): Response
    {
        abort_unless($rosterEntry->status === RosterStatus::Active, 404);

        return $this->present([$rosterEntry], $rosterEntry->entry, $issue, route('panel.entries.show', $rosterEntry->entry_id), true);
    }

    public function entry(Entry $entry, IssueAthleteCard $issue): Response
    {
        $members = $entry->rosterEntries()->where('status', RosterStatus::Active)->with('person')->get()
            ->sortBy([fn ($a, $b) => ($a->role === RosterRole::Athlete ? 0 : 1) <=> ($b->role === RosterRole::Athlete ? 0 : 1), fn ($a, $b) => ($a->shirt_number ?? 999) <=> ($b->shirt_number ?? 999), fn ($a, $b) => $a->person->name <=> $b->person->name])
            ->values()->all();

        return $this->present($members, $entry, $issue, route('panel.entries.show', $entry->getKey()), true);
    }

    public function regenerate(RosterEntry $rosterEntry, IssueAthleteCard $issue): RedirectResponse
    {
        $issue->handle($rosterEntry, regenerate: true);

        return back()->with('success', 'Novo código emitido. O QR da carteirinha antiga deixa de valer.');
    }

    /**
     * Também usada pela área do gestor (só das próprias equipes), que não renova códigos: `$canRegenerate` esconde o botão.
     *
     * @param  array<int, RosterEntry>  $members
     */
    public function present(array $members, Entry $entry, IssueAthleteCard $issue, string $backUrl, bool $canRegenerate): Response
    {
        $entry->load('team', 'division.season.competition');
        $division = $entry->division;
        $season = $division->season;

        $cards = array_map(function (RosterEntry $member) use ($issue, $entry, $division, $season) {
            $code = $issue->handle($member);
            $person = $member->person;

            return [
                'id' => $member->getKey(),
                'code' => $code,
                'verify_url' => route('portal.card', $code),
                'name' => $person->name,
                'nickname' => $person->nickname,
                'birth_date' => $person->birth_date?->format('d/m/Y'),
                'role_label' => $member->role->label(),
                'is_athlete' => $member->role === RosterRole::Athlete,
                'shirt_number' => $member->shirt_number,
                'photo_url' => $person->photoUrl(400),
                'team' => $entry->team->name,
                'team_logo_url' => $entry->team->logoUrl(128),
                'competition' => $season->competition->name,
                'season' => $season->name,
                'division' => $division->name,
            ];
        }, array_values($members));

        return Inertia::render('Panel/Cards/Show', [
            'cards' => $cards,
            'entry' => ['id' => $entry->getKey(), 'team' => $entry->team->name],
            'back_url' => $backUrl,
            'can_regenerate' => $canRegenerate,
        ]);
    }
}
