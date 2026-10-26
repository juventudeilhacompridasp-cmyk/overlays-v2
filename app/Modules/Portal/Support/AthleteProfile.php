<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;

/**
 * Perfil público de um atleta (`/atletas/{pessoa}`). Só existe com TRÊS condições juntas: o interruptor `athlete_profile` do portal
 * (desligado por padrão), a autorização registrada no cadastro (`people.public_profile_at`) e maioridade comprovada (nascimento
 * informado e 18 anos ou mais; menor ou idade desconhecida nunca tem perfil). Mostra nome público, equipes, edições publicadas,
 * camisa, jogos e gols. Nunca foto, nascimento, idade, documento, cartões ou contato.
 */
final class AthleteProfile
{
    public static function eligible(Person $person): bool
    {
        return $person->public_profile_at !== null && $person->birth_date !== null && $person->birth_date->age >= 18;
    }

    public static function enabled(): bool
    {
        return PortalSettings::enabled('athlete_profile');
    }

    public static function url(Person $person): ?string
    {
        return self::enabled() && self::eligible($person) ? route('portal.athlete', $person->getKey()) : null;
    }

    /** @return array{name: string, participations: list<array<string, mixed>>, totals: array{games: int, goals: int}}|null */
    public function for(Person $person): ?array
    {
        if (! self::enabled() || ! self::eligible($person)) {
            return null;
        }

        $rows = RosterEntry::query()
            ->where('person_id', $person->getKey())
            ->where('role', RosterRole::Athlete->value)
            ->whereHas('entry', fn ($entry) => $entry->where('status', EntryStatus::Confirmed->value)->whereHas('division.season', fn ($season) => $season->published()))
            ->with(['entry.team', 'entry.division.season.competition'])
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $entryIds = $rows->pluck('entry_id');

        $games = GameLineup::query()->where('person_id', $person->getKey())->whereIn('entry_id', $entryIds)
            ->whereHas('game', fn ($game) => $game->whereIn('status', [GameStatus::Finished->value, GameStatus::Walkover->value]))
            ->get(['entry_id', 'game_id'])->groupBy('entry_id')->map(fn ($lineups) => $lineups->pluck('game_id')->unique()->count());

        $goals = GameEvent::query()->effective()->where('type', GameEventType::Goal->value)->where('person_id', $person->getKey())
            ->whereIn('entry_id', $entryIds)->get(['entry_id'])->countBy('entry_id');

        $participations = $rows
            ->sortByDesc(fn (RosterEntry $row) => [$row->entry->division->season->year, $row->entry->division->season->getKey()])
            ->map(function (RosterEntry $row) use ($games, $goals): array {
                /** @var Entry $entry */
                $entry = $row->entry;
                $season = $entry->division->season;

                return [
                    'team' => $entry->team->name,
                    'team_url' => route('portal.team', $entry->team->getKey()),
                    'competition' => $season->competition->name,
                    'season' => $season->name,
                    'division' => $entry->division->name,
                    'url' => route('portal.season', [$season->competition->slug, $season->getKey()]),
                    'shirt' => $row->shirt_number,
                    'games' => (int) ($games[$entry->getKey()] ?? 0),
                    'goals' => (int) ($goals[$entry->getKey()] ?? 0),
                ];
            })->values()->all();

        return [
            'name' => (string) PublicName::of($person),
            'participations' => $participations,
            'totals' => ['games' => array_sum(array_column($participations, 'games')), 'goals' => array_sum(array_column($participations, 'goals'))],
        ];
    }
}
