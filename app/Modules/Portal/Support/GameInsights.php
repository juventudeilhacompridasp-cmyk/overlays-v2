<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dados extras da página pública do jogo: linha do tempo, posição na tabela, forma recente e confronto direto.
 * Tudo público e parametrizável por organização (`PortalSettings`): só times, placares e nomes públicos (`PublicName`).
 * Cartões só entram se a organização ligar `game_cards`. Nunca escalação, disciplina individual nem dados pessoais.
 */
final class GameInsights
{
    public function __construct(private readonly CalculateStandings $standings = new CalculateStandings) {}

    /**
     * @return array{timeline: list<array<string, mixed>>|null, standings: array<string, mixed>|null, form: array<string, mixed>|null, head_to_head: array<string, mixed>|null, lineup: array<string, mixed>|null}
     */
    public function for(Game $game): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'stage', 'group', 'division.season.competition']);

        return [
            'timeline' => PortalSettings::enabled('game_timeline') && $game->sport()->profile()->scoreModel === 'goals' ? $this->timeline($game) : null,
            'standings' => PortalSettings::enabled('game_standings') ? $this->standing($game) : null,
            'form' => PortalSettings::enabled('game_form') ? $this->form($game) : null,
            'head_to_head' => PortalSettings::enabled('game_head_to_head') ? $this->headToHead($game) : null,
            'lineup' => PortalSettings::enabled('game_lineup') ? $this->lineup($game) : null,
        ];
    }

    /** @return array{home: array<string, list<array<string, mixed>>>, away: array<string, list<array<string, mixed>>>} */
    private function lineup(Game $game): array
    {
        $game->loadMissing(['lineups.person']);
        $teams = ['home' => ['starter' => [], 'bench' => []], 'away' => ['starter' => [], 'bench' => []]];

        foreach ($game->lineups as $row) {
            $team = $row->entry_id === $game->home_entry_id ? 'home' : ($row->entry_id === $game->away_entry_id ? 'away' : null);

            if ($team === null || $row->person === null || $row->slot === LineupSlot::Staff) {
                continue;
            }

            $teams[$team][$row->slot->value][] = [
                'name' => PublicName::of($row->person),
                'shirt' => $row->shirt_number === null ? null : (int) $row->shirt_number,
                'captain' => (bool) $row->is_captain,
            ];
        }

        return $teams;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeline(Game $game): array
    {
        $types = [GameEventType::Goal, GameEventType::OwnGoal, GameEventType::Substitution];

        if (PortalSettings::enabled('game_cards')) {
            $types = [...$types, GameEventType::YellowCard, GameEventType::RedCard];
        }

        $side = fn (?string $entryId): ?string => $entryId === $game->home_entry_id ? 'home' : ($entryId === $game->away_entry_id ? 'away' : null);

        return $game->events()->effective()->whereIn('type', array_map(fn (GameEventType $type) => $type->value, $types))
            ->with(['person', 'secondaryPerson'])->get()
            ->sortBy(fn (GameEvent $event) => [(int) $event->period, $event->minute ?? 999, $event->getKey()])
            ->map(function (GameEvent $event) use ($side): array {
                $own = $event->type === GameEventType::OwnGoal;
                $team = $side($event->entry_id);
                $substitution = $event->type === GameEventType::Substitution;

                return [
                    'type' => $event->type->value,
                    'side' => $own ? ($team === 'home' ? 'away' : 'home') : $team,
                    'minute' => $event->minute,
                    'period' => (int) $event->period,
                    // Substituição: `person` sai e `secondaryPerson` entra.
                    'player' => PublicName::of($substitution ? $event->secondaryPerson : $event->person),
                    'out' => $substitution ? PublicName::of($event->person) : null,
                    'own_goal' => $own,
                ];
            })->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function standing(Game $game): ?array
    {
        if ($game->group_id !== null) {
            $rows = $this->standings->forGroup($game->group);
            $title = $game->group->name;
        } elseif ($game->stage->type === StageType::League) {
            $rows = $this->standings->forStage($game->stage);
            $title = $game->stage->name;
        } else {
            return null;
        }

        $pick = function (?string $entryId) use ($rows): ?array {
            foreach ($rows as $item) {
                /** @var StandingEntry $item */
                if ($item->entry->getKey() === $entryId) {
                    return ['position' => $item->row->position, 'points' => $item->row->points, 'played' => $item->row->played, 'of' => count($rows)];
                }
            }

            return null;
        };

        $home = $pick($game->home_entry_id);
        $away = $pick($game->away_entry_id);

        return $home === null && $away === null ? null : ['title' => $title, 'home' => $home, 'away' => $away];
    }

    /**
     * @return array{home: list<array<string, mixed>>, away: list<array<string, mixed>>}|null
     */
    private function form(Game $game): ?array
    {
        $home = $game->homeEntry?->team_id;
        $away = $game->awayEntry?->team_id;

        if ($home === null && $away === null) {
            return null;
        }

        return [
            'home' => $home === null ? [] : $this->recent($game, $home),
            'away' => $away === null ? [] : $this->recent($game, $away),
        ];
    }

    /**
     * Últimos 5 jogos decididos da equipe (V/E/D do ponto de vista dela), antes deste jogo.
     *
     * @return list<array<string, mixed>>
     */
    private function recent(Game $game, string $teamId): array
    {
        return $this->previous($game)
            ->where(fn (Builder $query) => $query
                ->whereHas('homeEntry', fn ($entry) => $entry->where('team_id', $teamId))
                ->orWhereHas('awayEntry', fn ($entry) => $entry->where('team_id', $teamId)))
            ->limit(5)->get()
            ->map(function (Game $past) use ($teamId): array {
                $isHome = $past->homeEntry?->team_id === $teamId;
                $for = $isHome ? $past->home_score : $past->away_score;
                $against = $isHome ? $past->away_score : $past->home_score;
                $opponent = $isHome ? $past->awayEntry : $past->homeEntry;

                return [
                    'result' => $for > $against ? 'V' : ($for < $against ? 'D' : 'E'),
                    'score' => "{$for} x {$against}",
                    'opponent' => $opponent?->team->name,
                    'home' => $isHome,
                    'url' => route('public.games.show', $past->getKey()),
                ];
            })->values()->all();
    }

    /**
     * @return array{games: list<array<string, mixed>>, summary: array{home_wins: int, draws: int, away_wins: int}}|null
     */
    private function headToHead(Game $game): ?array
    {
        $home = $game->homeEntry?->team_id;
        $away = $game->awayEntry?->team_id;

        if ($home === null || $away === null || $home === $away) {
            return null;
        }

        $past = $this->previous($game)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $pair) => $pair->whereHas('homeEntry', fn ($entry) => $entry->where('team_id', $home))->whereHas('awayEntry', fn ($entry) => $entry->where('team_id', $away)))
                ->orWhere(fn (Builder $pair) => $pair->whereHas('homeEntry', fn ($entry) => $entry->where('team_id', $away))->whereHas('awayEntry', fn ($entry) => $entry->where('team_id', $home))))
            ->limit(20)->get();

        if ($past->isEmpty()) {
            return null;
        }

        $summary = ['home_wins' => 0, 'draws' => 0, 'away_wins' => 0];

        foreach ($past as $item) {
            // "home" = a equipe mandante DESTE jogo, não do jogo antigo.
            $homeFor = $item->homeEntry?->team_id === $home ? $item->home_score : $item->away_score;
            $homeAgainst = $item->homeEntry?->team_id === $home ? $item->away_score : $item->home_score;
            $summary[$homeFor > $homeAgainst ? 'home_wins' : ($homeFor < $homeAgainst ? 'away_wins' : 'draws')]++;
        }

        return [
            'summary' => $summary,
            'games' => $past->take(5)->map(fn (Game $item): array => [
                'home' => $item->homeEntry?->team->name,
                'away' => $item->awayEntry?->team->name,
                'home_score' => $item->home_score,
                'away_score' => $item->away_score,
                'scheduled_at' => $item->scheduled_at?->format('Y-m-d'),
                'competition' => $item->division->season->name,
                'url' => route('public.games.show', $item->getKey()),
            ])->values()->all(),
        ];
    }

    /**
     * Jogos decididos de edições publicadas, anteriores a este, do mais recente para o mais antigo.
     *
     * @return Builder<Game>
     */
    private function previous(Game $game): Builder
    {
        return Game::query()
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->whereKeyNot($game->getKey())
            ->whereNotNull('home_entry_id')->whereNotNull('away_entry_id')
            ->whereHas('division.season', fn ($season) => $season->published())
            ->when($game->scheduled_at, fn (Builder $query, $at) => $query->where('scheduled_at', '<', $at))
            ->with(['homeEntry.team', 'awayEntry.team', 'division.season'])
            ->orderByDesc('scheduled_at')->orderByDesc('id');
    }
}
