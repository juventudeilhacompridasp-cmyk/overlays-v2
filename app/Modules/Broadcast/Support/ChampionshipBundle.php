<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Portal\Support\CompetitionPage;
use App\Modules\Portal\Support\PortalSettings;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Portal\Support\SeasonPresenter;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;

/**
 * Dados dos overlays de campeonato (`/overlay/{token}/campeonato/{visão}`): tabela, jogos, artilharia, cartões e campeão de
 * UMA divisão de uma edição publicada, no formato plano que o front desenha. Parte do {@see SeasonPresenter} (mesmos dados
 * públicos do portal); cartões só entram se a organização ligou `game_cards` e nunca trazem escalação, documentos ou dados
 * pessoais (nomes via {@see PublicName}). Edição em rascunho não existe aqui.
 */
final class ChampionshipBundle
{
    private const CARDS = 20;

    /** Edição pedida na URL (`edicao` ou `c`=slug da competição) ou, na falta, a do jogo no ar do canal. */
    public static function resolve(Request $request, ?Game $onAir): ?Season
    {
        $seasonId = $request->query('edicao');
        $slug = $request->query('c');

        if (is_string($seasonId) && $seasonId !== '') {
            return Season::query()->published()->with('competition')->find($seasonId);
        }

        if (is_string($slug) && $slug !== '') {
            $competition = Competition::query()->where('slug', $slug)->first();

            return $competition === null ? null : Season::query()->published()->with('competition')
                ->where('competition_id', $competition->getKey())->orderByDesc('year')->orderByDesc('created_at')->first();
        }

        $season = $onAir?->division?->season;

        return $season !== null && $season->status !== SeasonStatus::Draft ? $season->loadMissing('competition') : null;
    }

    /** @return array<string, mixed>|null */
    public static function for(Season $season, ?string $divisionId = null): ?array
    {
        $data = (new SeasonPresenter)->handle($season);
        $divisions = collect($data['divisions']);
        $picked = $divisionId !== null ? $divisions->firstWhere('id', $divisionId) : null;
        $division = $picked ?? $divisions->first();

        if ($division === null) {
            return null;
        }

        $model = Division::query()->find($division['id']);
        $teams = [];
        $note = function (?array $team) use (&$teams): void {
            if ($team !== null) {
                $teams[$team['id']] ??= ['id' => $team['id'], 'name' => $team['name'], 'short' => $team['short_name'], 'logo' => $team['logo_url'], 'color' => null];
            }
        };

        $standings = [];
        $groups = [];
        $matches = [];

        foreach ($division['stages'] as $stage) {
            foreach ($stage['tables'] as $index => $table) {
                $rows = array_map(function (array $row) use ($note): array {
                    $note($row['team']);

                    return [
                        'position' => $row['position'], 'team_id' => $row['team']['id'], 'name' => $row['team']['name'],
                        'played' => $row['played'], 'won' => $row['wins'], 'drawn' => $row['draws'], 'lost' => $row['losses'],
                        'gd' => $row['goal_difference'], 'points' => $row['points'],
                    ];
                }, $table['rows']);

                if ($stage['type'] === 'groups') {
                    $groups[] = ['group' => $table['name'], 'table' => $rows];
                } elseif ($standings === []) {
                    $standings = $rows;
                }
            }

            foreach ($stage['rounds'] as $round) {
                foreach ($round['games'] as $game) {
                    $note($game['home']);
                    $note($game['away']);
                    $matches[] = [
                        'id' => $game['id'],
                        'round' => $round['label'],
                        'status' => self::status($game['status']),
                        'kickoff_at' => $game['scheduled_at'],
                        'home_team_id' => $game['home']['id'] ?? null,
                        'away_team_id' => $game['away']['id'] ?? null,
                        'home_name' => $game['home']['name'] ?? 'A definir',
                        'away_name' => $game['away']['name'] ?? 'A definir',
                        'home_score' => $game['decided'] || $game['live'] ? $game['home_score'] : null,
                        'away_score' => $game['decided'] || $game['live'] ? $game['away_score'] : null,
                        'venue' => $game['venue'],
                    ];
                }
            }
        }

        $colors = Team::query()->whereIn('id', array_keys($teams))->pluck('primary_color', 'id');
        foreach ($teams as $id => &$team) {
            $team['color'] = $colors[$id] ?? null;
        }
        unset($team);

        $champion = $model === null ? null : (new CompetitionPage)->podium($season, $model)['champion'];

        return [
            'championship' => [
                'name' => $data['competition']['name'],
                'season' => $data['season']['name'],
                'division' => $division['name'],
                'logo' => null,
                'champion_id' => $champion['id'] ?? null,
            ],
            'divisions' => $divisions->map(fn (array $item) => ['id' => $item['id'], 'name' => $item['name']])->values()->all(),
            'teams' => array_values($teams),
            'standings' => $standings,
            'groups' => $groups,
            'matches' => $matches,
            'scorers' => array_map(fn (array $scorer) => [
                'name' => $scorer['name'], 'team_name' => $scorer['team'], 'team_id' => collect($teams)->firstWhere('name', $scorer['team'])['id'] ?? null, 'goals' => $scorer['goals'],
            ], $division['scorers']),
            'cards' => PortalSettings::enabled('game_cards') ? self::cards($division['id']) : null,
        ];
    }

    /** @return list<array{name: string, team_name: string|null, team_id: string|null, yellow: int, red: int}> */
    private static function cards(string $divisionId): array
    {
        $games = Game::query()->where('division_id', $divisionId)->with('homeEntry.team', 'awayEntry.team')->get();
        $teams = $games->flatMap(fn (Game $game) => [$game->homeEntry, $game->awayEntry])->filter()->unique('id')
            ->mapWithKeys(fn ($entry) => [$entry->getKey() => ['id' => $entry->team->getKey(), 'name' => $entry->team->name]]);

        $events = GameEvent::query()->effective()
            ->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard])
            ->whereNotNull('person_id')
            ->whereIn('game_id', $games->whereIn('status', [GameStatus::InProgress, GameStatus::Finished, GameStatus::Walkover])->pluck('id'))
            ->get(['person_id', 'entry_id', 'type']);

        $rows = $events->groupBy(fn (GameEvent $event) => $event->person_id.'|'.$event->entry_id)->map(fn ($group) => [
            'person_id' => $group->first()->person_id,
            'entry_id' => $group->first()->entry_id,
            'yellow' => $group->where('type', GameEventType::YellowCard)->count(),
            'red' => $group->where('type', GameEventType::RedCard)->count(),
        ])->sortByDesc(fn (array $row) => $row['red'] * 100 + $row['yellow'])->take(self::CARDS)->values();

        $people = Person::query()->whereIn('id', $rows->pluck('person_id'))->get()->keyBy('id');

        return $rows->map(fn (array $row) => [
            'name' => PublicName::of($people->get($row['person_id'])) ?? '—',
            'team_name' => $teams[$row['entry_id']]['name'] ?? null,
            'team_id' => $teams[$row['entry_id']]['id'] ?? null,
            'yellow' => $row['yellow'],
            'red' => $row['red'],
        ])->all();
    }

    private static function status(string $status): string
    {
        return match ($status) {
            GameStatus::InProgress->value => 'live',
            GameStatus::Finished->value, GameStatus::Walkover->value => 'finished',
            GameStatus::Cancelled->value => 'cancelled',
            default => 'scheduled',
        };
    }
}
