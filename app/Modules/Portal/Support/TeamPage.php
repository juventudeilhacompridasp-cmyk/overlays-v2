<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dados extras da página pública da equipe: campanha por edição, forma recente, artilheiros e, só se a organização ligar,
 * o elenco. Tudo vem de edições PUBLICADAS e respeita `PortalSettings` e `PublicName`: nunca CPF, documentos, fotos,
 * nascimento nem disciplina.
 */
final class TeamPage
{
    public function __construct(private readonly CalculateStandings $standings = new CalculateStandings) {}

    /**
     * @param  Collection<int, Entry>  $entries  inscrições confirmadas da equipe em edições publicadas
     * @return array<string, mixed>
     */
    public function for(Team $team, Collection $entries): array
    {
        $data = [
            'campaigns' => PortalSettings::enabled('team_history') ? $this->campaigns($entries) : null,
            'form' => PortalSettings::enabled('team_form') ? $this->form($team) : null,
            'scorers' => PortalSettings::enabled('team_scorers') ? $this->scorers($entries) : null,
            'calendar_url' => PortalSettings::enabled('team_calendar') ? route('portal.team.calendar', $team->getKey()) : null,
        ];

        // O elenco só existe na resposta quando a organização liga (a chave nem aparece com o interruptor desligado).
        if (PortalSettings::enabled('team_roster')) {
            $data['roster'] = $this->roster($entries);
        }

        return $data;
    }

    /**
     * Campanha em cada edição: jogos, saldo e colocação na tabela (do último grupo ou fase de pontos corridos em que a equipe aparece).
     *
     * @param  Collection<int, Entry>  $entries
     * @return list<array<string, mixed>>
     */
    private function campaigns(Collection $entries): array
    {
        $games = Game::query()
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->where(fn (Builder $query) => $query->whereIn('home_entry_id', $entries->pluck('id'))->orWhereIn('away_entry_id', $entries->pluck('id')))
            ->get();

        return $entries
            ->sortByDesc(fn (Entry $entry) => [$entry->division->season->year, $entry->division->season->getKey()])
            ->map(function (Entry $entry) use ($games): array {
                $record = ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'for' => 0, 'against' => 0];

                foreach ($games as $game) {
                    if (! in_array($entry->getKey(), [$game->home_entry_id, $game->away_entry_id], true)) {
                        continue;
                    }

                    $isHome = $game->home_entry_id === $entry->getKey();
                    $for = $isHome ? $game->home_score : $game->away_score;
                    $against = $isHome ? $game->away_score : $game->home_score;

                    $record['played']++;
                    $record['for'] += $for;
                    $record['against'] += $against;
                    $record[$for > $against ? 'wins' : ($for < $against ? 'losses' : 'draws')]++;
                }

                $season = $entry->division->season;

                return [
                    'competition' => $season->competition->name,
                    'season' => $season->name,
                    'division' => $entry->division->name,
                    'status' => $season->status->label(),
                    'url' => route('portal.season', [$season->competition->slug, $season->getKey()]),
                    'position' => $this->position($entry),
                ] + $record;
            })->values()->all();
    }

    /**
     * @return array{title: string, position: int, of: int, points: int}|null
     */
    private function position(Entry $entry): ?array
    {
        $found = null;

        foreach ($entry->division->stages()->orderBy('sequence')->get() as $stage) {
            $tables = match ($stage->type) {
                StageType::League => [[$stage->name, $this->standings->forStage($stage)]],
                StageType::Groups => $stage->groups()->orderBy('sequence')->get()->map(fn ($group) => [$group->name, $this->standings->forGroup($group)])->all(),
                default => [],
            };

            foreach ($tables as [$title, $rows]) {
                foreach ($rows as $item) {
                    if ($item->entry->getKey() === $entry->getKey()) {
                        $found = ['title' => $title, 'position' => $item->row->position, 'of' => count($rows), 'points' => $item->row->points];
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Últimos 5 jogos decididos da equipe, em edições publicadas.
     *
     * @return list<array<string, mixed>>
     */
    private function form(Team $team): array
    {
        return Game::query()
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereHas('division.season', fn ($season) => $season->published())
            ->where(fn (Builder $query) => $query
                ->whereHas('homeEntry', fn ($entry) => $entry->where('team_id', $team->getKey()))
                ->orWhereHas('awayEntry', fn ($entry) => $entry->where('team_id', $team->getKey())))
            ->with(['homeEntry.team', 'awayEntry.team'])
            ->orderByRaw('scheduled_at is null')->orderByDesc('scheduled_at')->orderByDesc('id')->limit(5)->get()
            ->map(function (Game $game) use ($team): array {
                $isHome = $game->homeEntry?->team_id === $team->getKey();
                $for = $isHome ? $game->home_score : $game->away_score;
                $against = $isHome ? $game->away_score : $game->home_score;
                $opponent = $isHome ? $game->awayEntry : $game->homeEntry;

                return [
                    'result' => $for > $against ? 'V' : ($for < $against ? 'D' : 'E'),
                    'score' => "{$for} x {$against}",
                    'opponent' => $opponent?->team->name,
                    'home' => $isHome,
                    'url' => route('public.games.show', $game->getKey()),
                ];
            })->values()->all();
    }

    /**
     * Os 5 maiores artilheiros da equipe (gols a favor, sem gol contra), pelo nome público.
     *
     * @param  Collection<int, Entry>  $entries
     * @return list<array{player: string, goals: int}>
     */
    private function scorers(Collection $entries): array
    {
        $counts = GameEvent::query()->effective()
            ->where('type', GameEventType::Goal->value)
            ->whereIn('entry_id', $entries->pluck('id'))
            ->whereNotNull('person_id')
            ->get(['id', 'person_id'])
            ->countBy('person_id')->sortDesc()->take(5);

        $people = Person::query()->whereIn('id', $counts->keys())->get(['id', 'name', 'nickname', 'birth_date'])->keyBy('id');

        return $counts->map(fn (int $goals, string $personId) => ['player' => (string) PublicName::of($people[$personId] ?? null), 'goals' => $goals])
            ->filter(fn (array $item) => $item['player'] !== '')
            ->values()->all();
    }

    /**
     * Elenco ativo da edição mais recente: atletas e técnico, só nome público e camisa (nada de foto, nascimento ou documento).
     *
     * @param  Collection<int, Entry>  $entries
     * @return list<array<string, mixed>>
     */
    private function roster(Collection $entries): array
    {
        $latest = $entries->sortByDesc(fn (Entry $entry) => [$entry->division->season->year, $entry->division->season->getKey()])->first();

        if ($latest === null) {
            return [];
        }

        // O link do perfil só entra na resposta com o interruptor `athlete_profile` ligado (a chave nem existe desligado).
        $withProfiles = AthleteProfile::enabled();

        return RosterEntry::query()
            ->where('entry_id', $latest->getKey())
            ->where('status', RosterStatus::Active)
            ->whereIn('role', [RosterRole::Athlete->value, RosterRole::Coach->value])
            ->with('person')->get()
            ->sortBy(fn (RosterEntry $row) => [$row->role === RosterRole::Athlete ? 0 : 1, $row->shirt_number ?? 999, (string) $row->person->name])
            ->map(fn (RosterEntry $row) => [
                'name' => PublicName::of($row->person),
                'shirt' => $row->role === RosterRole::Athlete ? $row->shirt_number : null,
                'role' => $row->role->label(),
            ] + ($withProfiles && $row->role === RosterRole::Athlete ? ['profile_url' => AthleteProfile::url($row->person)] : []))->values()->all();
    }
}
