<?php

namespace App\Modules\Reports\Support;

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Data\StandingEntry;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Group;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Portal\Support\PortalSettings;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Teams\Models\Person;
use Illuminate\Support\Collection;

/**
 * Relatório de uma DIVISÃO de uma edição: andamento, classificação, mata-mata, artilharia, ataque e defesa, placar por
 * rodada, destaques, forma e disciplina (por equipe, nunca por atleta). Estatísticas de placar usam só jogos encerrados em
 * campo (W.O. conta no andamento, não nas médias). Com `$public` (overlay): nomes por PublicName e disciplina só com
 * `game_cards` ligado.
 */
final class ChampionshipReport
{
    private Division $division;

    private bool $public;

    private string $model;

    /** @var array{plural: string, singular: string, label: string} */
    private array $unit;

    /** @var Collection<int, Game> */
    private Collection $games;

    /** @var Collection<int, Game> jogos encerrados em campo */
    private Collection $played;

    /** @var array<string, string> cor de cada inscrição */
    private array $colors = [];

    /**
     * @param  list<string>|null  $sections
     * @return array<string, mixed>
     */
    public static function build(Division $division, ?array $sections = null, bool $public = false): array
    {
        return (new self)->make($division, ReportSections::pick(ReportKind::Championship, $sections), $public);
    }

    /**
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    private function make(Division $division, array $sections, bool $public): array
    {
        $division->loadMissing('season.competition');
        $this->division = $division;
        $this->public = $public;
        $this->model = $division->season->competition->sport->profile()->scoreModel;
        $this->unit = ReportKit::unit($this->model);
        $this->games = Game::query()->where('division_id', $division->getKey())
            ->with(['homeEntry.team', 'awayEntry.team', 'stage', 'group'])
            ->orderBy('scheduled_at')->orderBy('round')->orderBy('number')->orderBy('id')->get();
        $this->played = $this->games->filter(fn (Game $game) => $game->status === GameStatus::Finished && $game->home_entry_id !== null && $game->away_entry_id !== null)->values();

        $entries = Entry::query()->where('division_id', $division->getKey())->with('team')->orderBy('seed')->orderBy('id')->get();
        foreach ($entries->values() as $index => $entry) {
            $this->colors[(string) $entry->getKey()] = ReportKit::color($entry->team->primary_color, $index);
        }

        $season = $division->season;
        $dates = $this->games->pluck('scheduled_at')->filter();

        return ReportKit::finish([
            'kind' => ReportKind::Championship->value,
            'title' => ReportKind::Championship->label(),
            'subtitle' => $season->competition->name.' · '.$season->name,
            'organization' => ReportKit::organization(),
            'accent' => ReportKit::accent(),
            'private' => ! $public,
            'generated_at' => now()->format('d/m/Y H:i'),
            'meta' => array_filter([
                ['icon' => 'flag', 'label' => 'Divisão', 'value' => $division->name],
                ['icon' => 'ball', 'label' => 'Esporte', 'value' => $season->competition->sport->label()],
                ['icon' => 'users', 'label' => 'Equipes', 'value' => $entries->where('status', EntryStatus::Confirmed)->count().' equipes'],
                $dates->isNotEmpty() ? ['icon' => 'calendar', 'label' => 'Período', 'value' => $dates->min()->format('d/m/Y').' a '.$dates->max()->format('d/m/Y')] : null,
            ]),
            'hero' => null,
            'sections' => array_map(fn (string $key) => [
                'key' => $key,
                'title' => $key === 'scorers' ? $this->scorersTitle() : ReportSections::CHAMPIONSHIP[$key]['label'],
                'icon' => ReportSections::CHAMPIONSHIP[$key]['icon'],
                'blocks' => array_values(array_filter($this->section($key))),
            ], $sections),
        ]);
    }

    /** @return list<array<string, mixed>|null> */
    private function section(string $key): array
    {
        return match ($key) {
            'overview' => $this->overview(),
            'standings' => $this->standings(),
            'knockout' => $this->knockout(),
            'scorers' => $this->scorers(),
            'attack_defense' => $this->attackDefense(),
            'rounds' => $this->rounds(),
            'results' => $this->results(),
            'form' => $this->form(),
            'discipline' => $this->discipline(),
            default => [],
        };
    }

    /** @return list<array<string, mixed>|null> */
    private function overview(): array
    {
        $total = $this->games->whereNotIn('status', [GameStatus::Cancelled])->count();
        $decided = $this->games->filter(fn (Game $game) => $game->status->isDecided())->count();
        $goals = $this->played->sum(fn (Game $game) => $game->home_score + $game->away_score);
        $count = $this->played->count();
        $homeWins = $this->played->filter(fn (Game $game) => $game->home_score > $game->away_score)->count();
        $draws = $this->played->filter(fn (Game $game) => $game->home_score === $game->away_score)->count();
        $awayWins = $count - $homeWins - $draws;
        $progress = $total > 0 ? (int) round($decided / $total * 100) : 0;

        return [
            ReportKit::kpis([
                ['icon' => 'calendar', 'label' => 'Jogos programados', 'value' => (string) $total],
                ['icon' => 'flag', 'label' => 'Jogos realizados', 'value' => (string) $decided, 'hint' => $progress.'% da tabela', 'progress' => $progress],
                ['icon' => $this->model === 'goals' ? 'ball' : 'target', 'label' => ucfirst($this->unit['plural']).' marcados', 'value' => ReportKit::number($goals), 'color' => ReportKit::WIN],
                ['icon' => 'percent', 'label' => 'Média por jogo', 'value' => ReportKit::number($count > 0 ? $goals / $count : 0, 2)],
            ]),
            $count > 0 ? ReportKit::donut('Resultados dos jogos', [
                ['label' => 'Vitórias do mandante', 'value' => $homeWins, 'color' => ReportKit::color(null, 0)],
                ['label' => 'Empates', 'value' => $draws, 'color' => ReportKit::DRAW],
                ['label' => 'Vitórias do visitante', 'value' => $awayWins, 'color' => ReportKit::color(null, 1)],
            ], 'jogos') : null,
            $total > 0 ? ReportKit::donut('Situação dos jogos', [
                ['label' => 'Encerrados', 'value' => $this->games->where('status', GameStatus::Finished)->count(), 'color' => ReportKit::WIN],
                ['label' => 'W.O.', 'value' => $this->games->where('status', GameStatus::Walkover)->count(), 'color' => '#f97316'],
                ['label' => 'Em andamento', 'value' => $this->games->where('status', GameStatus::InProgress)->count(), 'color' => '#ef4444'],
                ['label' => 'Agendados', 'value' => $this->games->where('status', GameStatus::Scheduled)->count(), 'color' => '#0ea5e9'],
                ['label' => 'Adiados ou suspensos', 'value' => $this->games->whereIn('status', [GameStatus::Postponed, GameStatus::Suspended])->count(), 'color' => ReportKit::DRAW],
            ], 'jogos') : null,
        ];
    }

    /** @return list<array<string, mixed>|null> */
    private function standings(): array
    {
        $calculator = app(CalculateStandings::class);
        $blocks = [];

        foreach ($this->stages([StageType::League, StageType::Groups]) as $stage) {
            if ($stage->type === StageType::League) {
                $blocks[] = self::standingsTable($stage->name, $calculator->forStage($stage), $this->model);

                continue;
            }

            foreach ($stage->groups()->orderBy('name')->get() as $group) {
                /** @var Group $group */
                $blocks[] = self::standingsTable($stage->name.' · '.$group->name, $calculator->forGroup($group), $this->model);
            }
        }

        return $blocks === [] ? [ReportKit::note('A divisão ainda não tem fase de pontos corridos ou de grupos com jogos.')] : $blocks;
    }

    /**
     * Tabela de classificação como bloco (também usada no relatório da partida). Destaque: o líder, ou as equipes do jogo.
     *
     * @param  list<StandingEntry>  $entries
     * @param  list<string|null>  $highlight
     * @return array<string, mixed>
     */
    public static function standingsTable(string $title, array $entries, string $model, array $highlight = []): array
    {
        $columns = [
            ['key' => 'position', 'label' => '#', 'align' => 'center', 'width' => 1],
            ['key' => 'team', 'label' => 'Equipe', 'width' => 6],
            ['key' => 'points', 'label' => 'P', 'align' => 'center', 'width' => 1],
            ['key' => 'played', 'label' => 'J', 'align' => 'center', 'width' => 1],
            ['key' => 'won', 'label' => 'V', 'align' => 'center', 'width' => 1],
        ];

        if ($model === 'goals') {
            $columns[] = ['key' => 'drawn', 'label' => 'E', 'align' => 'center', 'width' => 1];
        }

        array_push($columns,
            ['key' => 'lost', 'label' => 'D', 'align' => 'center', 'width' => 1],
            ['key' => 'for', 'label' => 'Pró', 'align' => 'center', 'width' => 1],
            ['key' => 'against', 'label' => 'Contra', 'align' => 'center', 'width' => 1],
            ['key' => 'difference', 'label' => 'Saldo', 'align' => 'center', 'width' => 1],
        );

        $rows = array_map(fn (StandingEntry $item) => [
            'cells' => [
                'position' => $item->row->position.'º', 'team' => $item->entry->team->name, 'points' => $item->row->points, 'played' => $item->row->played,
                'won' => $item->row->wins, 'drawn' => $item->row->draws, 'lost' => $item->row->losses, 'for' => $item->row->goalsFor,
                'against' => $item->row->goalsAgainst, 'difference' => ($item->row->goalDifference() > 0 ? '+' : '').$item->row->goalDifference(),
            ],
            'highlight' => $highlight === [] ? $item->row->position === 1 && $item->row->played > 0 : in_array($item->entry->getKey(), $highlight, true),
            'color' => ReportKit::color($item->entry->team->primary_color),
        ], $entries);

        return ReportKit::table($title, $columns, $rows);
    }

    /** @return list<array<string, mixed>|null> */
    private function knockout(): array
    {
        $blocks = [];

        foreach ($this->stages([StageType::Knockout]) as $stage) {
            foreach ($this->games->where('stage_id', $stage->getKey())->groupBy(fn (Game $game) => (int) $game->round) as $round => $games) {
                $blocks[] = ReportKit::list($stage->name.($round > 0 ? ' · rodada '.$round : ''), $games->map(fn (Game $game) => [
                    'title' => ReportKit::team($game->homeEntry).' x '.ReportKit::team($game->awayEntry),
                    'detail' => $game->status->isDecided() || $game->status === GameStatus::InProgress
                        ? $game->status->label().($game->home_penalties !== null ? ' · pênaltis '.$game->home_penalties.' x '.$game->away_penalties : '')
                        : ($game->scheduled_at?->format('d/m/Y H:i') ?? 'Data a definir'),
                    'value' => $game->status === GameStatus::Scheduled ? null : $game->home_score.' x '.$game->away_score,
                    'icon' => 'flag',
                ])->values()->all());
            }
        }

        return $blocks === [] ? [ReportKit::note('A divisão não tem fase de mata-mata com jogos.')] : $blocks;
    }

    private function scorersTitle(): string
    {
        return match ($this->model) {
            'points' => 'Cestinhas',
            'sets' => 'Maiores pontuadores',
            default => 'Artilharia',
        };
    }

    /** @return list<array<string, mixed>|null> */
    private function scorers(): array
    {
        $types = match ($this->model) {
            'points' => GameEventType::scoring(),
            'sets' => [GameEventType::Point],
            default => [GameEventType::Goal],
        };

        $totals = [];

        GameEvent::query()->effective()
            ->whereIn('game_id', $this->games->pluck('id'))
            ->whereIn('type', $types)
            ->whereNotNull('person_id')
            ->get(['person_id', 'entry_id', 'type', 'payload'])
            ->each(function (GameEvent $event) use (&$totals) {
                $key = (string) $event->person_id;
                $totals[$key] ??= ['entry' => (string) $event->entry_id, 'value' => 0];
                $totals[$key]['value'] += $this->model === 'points' ? (int) (($event->payload ?? [])['points'] ?? $event->type->pointValue() ?? 0) : 1;
            });

        if ($totals === []) {
            return [ReportKit::note('Ainda não há '.$this->unit['plural'].' com autor registrado nesta divisão.')];
        }

        uasort($totals, fn (array $a, array $b) => $b['value'] <=> $a['value']);
        $top = array_slice($totals, 0, 10, true);
        $people = Person::query()->whereIn('id', array_keys($top))->get()->keyBy('id');
        $teams = Entry::query()->whereIn('id', array_column($top, 'entry'))->with('team')->get()->keyBy('id');

        $items = [];
        foreach ($top as $personId => $row) {
            $items[] = [
                'label' => ReportKit::name($people->get($personId), $this->public) ?? '—',
                'sub' => $teams->get($row['entry'])?->team->name,
                'value' => $row['value'],
                'color' => $this->colors[$row['entry']] ?? null,
            ];
        }

        return [ReportKit::ranking('Top 10 · '.$this->unit['plural'], $items, $this->unit['plural'])];
    }

    /** @return list<array<string, mixed>|null> */
    private function attackDefense(): array
    {
        $teams = $this->teamTotals();

        if ($teams === []) {
            return [ReportKit::note('Os números aparecem depois do primeiro jogo encerrado.')];
        }

        $byAttack = $teams;
        usort($byAttack, fn (array $a, array $b) => [$b['for'], $a['against']] <=> [$a['for'], $b['against']]);
        $avg = fn (array $row, string $key) => $row['played'] > 0 ? $row[$key] / $row['played'] : 0;
        $bestAttack = collect($teams)->sortByDesc(fn (array $row) => $avg($row, 'for'))->first();
        $bestDefense = collect($teams)->sortBy(fn (array $row) => $avg($row, 'against'))->first();
        $mostWins = collect($teams)->sortByDesc('wins')->first();

        return [
            ReportKit::kpis([
                ['icon' => 'bolt', 'label' => 'Melhor ataque · '.$bestAttack['name'], 'value' => ReportKit::number($avg($bestAttack, 'for'), 2), 'hint' => 'média por jogo', 'color' => ReportKit::WIN],
                ['icon' => 'shield', 'label' => 'Melhor defesa · '.$bestDefense['name'], 'value' => ReportKit::number($avg($bestDefense, 'against'), 2), 'hint' => 'sofridos por jogo', 'color' => '#0ea5e9'],
                ['icon' => 'trophy', 'label' => 'Mais vitórias · '.$mostWins['name'], 'value' => (string) $mostWins['wins'], 'color' => '#eab308'],
            ]),
            ReportKit::columns(ucfirst($this->unit['plural']).' pró e contra', [
                ['label' => 'Pró', 'color' => ReportKit::WIN],
                ['label' => 'Contra', 'color' => ReportKit::LOSS],
            ], array_map(fn (array $row) => ['label' => $row['short'], 'values' => [$row['for'], $row['against']]], array_slice($byAttack, 0, 16)), $this->unit['plural']),
        ];
    }

    /** @return list<array<string, mixed>|null> */
    private function rounds(): array
    {
        if ($this->played->isEmpty()) {
            return [ReportKit::note('Os números aparecem depois do primeiro jogo encerrado.')];
        }

        $byRound = $this->played->every(fn (Game $game) => $game->round !== null);
        $groups = $this->played->groupBy(fn (Game $game) => $byRound ? sprintf('%04d', $game->round) : ($game->scheduled_at?->format('Y-m-d') ?? '9999'))->sortKeys();

        $categories = $groups->map(fn (Collection $games, string $key) => [
            'label' => $byRound ? 'R'.(int) $key : ($key === '9999' ? 'S/D' : substr($key, 8, 2).'/'.substr($key, 5, 2)),
            'values' => [$games->sum(fn (Game $game) => $game->home_score + $game->away_score), $games->count()],
        ])->values()->take(-24)->values()->all();

        return [ReportKit::columns(ucfirst($this->unit['plural']).' por '.($byRound ? 'rodada' : 'data'), [
            ['label' => ucfirst($this->unit['plural']), 'color' => ReportKit::accent()],
            ['label' => 'Jogos', 'color' => ReportKit::DRAW],
        ], $categories, $this->unit['plural'])];
    }

    /** @return list<array<string, mixed>|null> */
    private function results(): array
    {
        if ($this->played->isEmpty()) {
            return [ReportKit::note('Os destaques aparecem depois do primeiro jogo encerrado.')];
        }

        $item = fn (Game $game) => [
            'title' => ReportKit::team($game->homeEntry).' x '.ReportKit::team($game->awayEntry),
            'detail' => $game->stage->name.($game->round !== null ? ' · rodada '.$game->round : '').' · '.($game->scheduled_at?->format('d/m/Y') ?? 'sem data'),
            'value' => $game->home_score.' x '.$game->away_score,
        ];

        $wins = $this->played->filter(fn (Game $game) => $game->home_score !== $game->away_score)
            ->sortByDesc(fn (Game $game) => [abs($game->home_score - $game->away_score), $game->home_score + $game->away_score])->take(5);
        $scoring = $this->played->sortByDesc(fn (Game $game) => $game->home_score + $game->away_score)->take(5);

        return array_values(array_filter([
            $wins->isNotEmpty() ? ReportKit::list('Maiores vitórias', $wins->map(fn (Game $game) => [...$item($game), 'icon' => 'bolt', 'color' => ReportKit::WIN])->values()->all()) : null,
            ReportKit::list('Jogos com mais '.$this->unit['plural'], $scoring->map(fn (Game $game) => [...$item($game), 'icon' => 'star', 'color' => '#eab308'])->values()->all()),
        ]));
    }

    /** @return list<array<string, mixed>|null> */
    private function form(): array
    {
        $rows = [];

        foreach ($this->games->filter(fn (Game $game) => $game->status->isDecided()) as $game) {
            foreach ([$game->homeEntry, $game->awayEntry] as $entry) {
                if ($entry === null) {
                    continue;
                }

                $id = (string) $entry->getKey();
                $rows[$id] ??= ['label' => $entry->team->name, 'color' => $this->colors[$id] ?? ReportKit::color(null), 'results' => []];
                $rows[$id]['results'][] = ReportKit::result($game, $id);
            }
        }

        if ($rows === []) {
            return [ReportKit::note('A forma recente aparece depois do primeiro jogo decidido.')];
        }

        $rows = array_map(fn (array $row) => [...$row, 'results' => array_slice($row['results'], -5)], $rows);
        usort($rows, fn (array $a, array $b) => strcoll($a['label'], $b['label']));

        return [ReportKit::form('Últimos 5 jogos de cada equipe', array_slice($rows, 0, 24))];
    }

    /** @return list<array<string, mixed>|null> */
    private function discipline(): array
    {
        if ($this->public && ! PortalSettings::enabled('game_cards')) {
            return [ReportKit::note('Os cartões não são exibidos publicamente nesta organização.')];
        }

        $byTeam = [];

        GameEvent::query()->effective()
            ->whereIn('game_id', $this->games->pluck('id'))
            ->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard, GameEventType::BlueCard])
            ->whereNotNull('entry_id')
            ->get(['entry_id', 'type'])
            ->each(function (GameEvent $event) use (&$byTeam) {
                $key = (string) $event->entry_id;
                $byTeam[$key] ??= ['yellow' => 0, 'red' => 0, 'blue' => 0];
                $byTeam[$key][match ($event->type) {
                    GameEventType::RedCard => 'red',
                    GameEventType::BlueCard => 'blue',
                    default => 'yellow',
                }]++;
            });

        if ($byTeam === []) {
            return [ReportKit::note('Nenhum cartão registrado nesta divisão.')];
        }

        $entries = Entry::query()->whereIn('id', array_keys($byTeam))->with('team')->get()->keyBy('id');
        $yellow = array_sum(array_column($byTeam, 'yellow'));
        $red = array_sum(array_column($byTeam, 'red'));
        $blue = array_sum(array_column($byTeam, 'blue'));
        $decided = max(1, $this->played->count());
        uasort($byTeam, fn (array $a, array $b) => ($b['yellow'] + $b['red'] * 3 + $b['blue'] * 2) <=> ($a['yellow'] + $a['red'] * 3 + $a['blue'] * 2));

        $series = [['label' => 'Amarelos', 'color' => '#eab308'], ['label' => 'Vermelhos', 'color' => '#dc2626']];
        if ($blue > 0) {
            $series[] = ['label' => 'Azuis', 'color' => '#2563eb'];
        }

        return [
            ReportKit::kpis(array_values(array_filter([
                ['icon' => 'card', 'label' => 'Cartões amarelos', 'value' => (string) $yellow, 'color' => '#eab308'],
                ['icon' => 'card', 'label' => 'Cartões vermelhos', 'value' => (string) $red, 'color' => '#dc2626'],
                $blue > 0 ? ['icon' => 'card', 'label' => 'Cartões azuis', 'value' => (string) $blue, 'color' => '#2563eb'] : null,
                ['icon' => 'percent', 'label' => 'Cartões por jogo', 'value' => ReportKit::number(($yellow + $red + $blue) / $decided, 2)],
            ]))),
            ReportKit::columns('Cartões por equipe', $series, array_map(fn (string $id, array $row) => [
                'label' => ReportKit::short($entries->get($id)),
                'values' => array_slice([$row['yellow'], $row['red'], $row['blue']], 0, count($series)),
            ], array_keys(array_slice($byTeam, 0, 16, true)), array_slice($byTeam, 0, 16, true))),
        ];
    }

    /** @return list<array{name: string, short: string, played: int, wins: int, for: int, against: int}> */
    private function teamTotals(): array
    {
        $teams = [];

        foreach ($this->played as $game) {
            foreach ([[$game->homeEntry, $game->home_score, $game->away_score], [$game->awayEntry, $game->away_score, $game->home_score]] as [$entry, $for, $against]) {
                $id = (string) $entry->getKey();
                $teams[$id] ??= ['name' => $entry->team->name, 'short' => ReportKit::short($entry), 'played' => 0, 'wins' => 0, 'for' => 0, 'against' => 0];
                $teams[$id]['played']++;
                $teams[$id]['wins'] += $for > $against ? 1 : 0;
                $teams[$id]['for'] += $for;
                $teams[$id]['against'] += $against;
            }
        }

        return array_values($teams);
    }

    /**
     * @param  list<StageType>  $types
     * @return Collection<int, Stage>
     */
    private function stages(array $types): Collection
    {
        $withGames = $this->games->pluck('stage_id')->unique()->all();

        return $this->division->stages()->orderBy('sequence')->get()
            ->filter(fn (Stage $stage) => in_array($stage->type, $types, true) && in_array($stage->getKey(), $withGames, true))
            ->values();
    }
}
