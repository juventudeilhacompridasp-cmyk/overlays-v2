<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use Inertia\Inertia;
use Inertia\Response;

/** Verificação pública da carteirinha (QR). Só nome público, equipe, função e situação — sem foto, CPF ou nascimento. */
class CardVerifyController extends Controller
{
    public function __invoke(string $code): Response
    {
        $member = RosterEntry::query()
            ->where('card_code', strtoupper($code))
            ->with(['person', 'entry.team', 'entry.division.season.competition'])
            ->first();

        abort_if($member === null || ! $member->entry->division->season->isPublished(), 404);

        $entry = $member->entry;
        $season = $entry->division->season;
        $theme = PublicTheme::current();
        $valid = $member->status === RosterStatus::Active && $entry->status === EntryStatus::Confirmed;

        return Inertia::render('Portal/Card', [
            'theme' => $theme,
            'seo' => ['title' => "Verificação de carteirinha · {$theme['name']}", 'description' => 'Verificação de carteirinha.', 'url' => route('portal.card', $member->card_code), 'image' => null],
            'card' => [
                'valid' => $valid,
                'status_label' => $valid ? 'Carteirinha válida' : 'Carteirinha sem validade',
                'name' => PublicName::of($member->person),
                'role_label' => $member->role->label(),
                'shirt_number' => $member->shirt_number,
                'team' => $entry->team->name,
                'team_logo_url' => $entry->team->logoUrl(128),
                'competition' => $season->competition->name,
                'season' => $season->name,
                'division' => $entry->division->name,
            ],
        ]);
    }
}
