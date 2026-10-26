<?php

namespace App\Modules\Reports\Support;

use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Portal\Support\PortalSettings;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Rules\Tactics\Formations;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Support\Collection;

/**
 * Relatório de UMA partida como documento de blocos (placar, comparativo, períodos, linha do tempo, escalações, tabela e
 * momento). Só eventos válidos (`GameEvent::effective()`). Com `$public` (overlay) os nomes passam por PublicName e os
 * cartões só entram com `game_cards` ligado; o PDF interno (`$public = false`) traz tudo. Nunca IDs, documentos,
 * nascimento, fotos ou instruções táticas.
 */
final class GameReport
{
    private Game $game;

    private bool $public;

    private bool $cards;

    private string $model;

    /** @var array{plural: string, singular: string, label: string} */
    private array $unit;

    /** @var Collection<int, GameEvent> */
    private Collection $events;

    private string $homeColor;

    private string $awayColor;

    /**
     * @param  list<string>|null  $sections
     * @return array<string, mixed>
     */
    public static function build(Game $game, ?array $sections = null, bool $public = false): array
    {
        return (new self)->make($game, ReportSections::pick(ReportKind::Game, $sections), $public);
    }

    /**
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    private function make(Game $game, array $sections, bool $public): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'division.season.competition', 'stage', 'group', 'venue']);
        $this->game = $game;
        $this->public = $public;
        $this->cards = ! $public || PortalSettings::enabled('game_cards');
        $this->model = $game->sport()->profile()->scoreModel;
        $this->unit = ReportKit::unit($this->model);
        $this->events = $game->events()->effective()->with(['person', 'secondaryPerson'])->orderBy('id')->get();
        [$this->homeColor, $this->awayColor] = ReportKit::pair($game->homeEntry?->team->primary_color, $game->awayEntry?->team->primary_color);

        $season = $game->division->season;
        $place = array_filter([$game->stage->name, $game->group?->name, $game->round !== null ? 'Rodada '.$game->round : null]);

        return ReportKit::finish([
            'kind' => ReportKind::Game->value,
            'title' => ReportKind::Game->label(),
            'subtitle' => ReportKit::team($game->homeEntry).' x '.ReportKit::team($game->awayEntry),
            'organization' => ReportKit::organization(),
            'accent' => ReportKit::accent(),
            'private' => ! $public,
            'generated_at' => now()->format('d/m/Y H:i'),
            'meta' => array_filter([
                ['icon' => 'trophy', 'label' => 'Competição', 'value' => $season->competition->name.' · '.$season->name.' · '.$game->division->name],
                ['icon' => 'flag', 'label' => 'Fase', 'value' => implode(' · ', $place)],
                ['icon' => 'calendar', 'label' => 'Data', 'value' => $game->scheduled_at?->format('d/m/Y H:i') ?? 'A definir'],
                $game->venue !== null ? ['icon' => 'pin', 'label' => 'Local', 'value' => $game->venue->name] : null,
            ]),
            'hero' => $this->hero(),
            'sections' => array_map(fn (string $key) => [
                'key' => $key,
                'title' => ReportSections::GAME[$key]['label'],
                'icon' => ReportSections::GAME[$key]['icon'],
                'blocks' => array_values(array_filter($this->section($key))),
            ], $sections),
        ]);
    }

    /** @return list<array<string, mixed>|null> */
    private function section(string $key): array
    {
        return match ($key) {
            'overview' => $this->overview(),
            'comparison' => $this->comparison(),
            'periods' => $this->periods(),
            'timeline' => $this->timeline(),
            'lineups' => $this->lineups(),
            'standings' => $this->standings(),
            'form' => $this->form(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function hero(): array
    {
        $game = $this->game;
        $side = fn (?Entry $entry, int $score, string $color, string $image) => [
            'name' => ReportKit::team($entry), 'short' => ReportKit::short($entry), 'color' => $color, 'score' => $score,
            'logo_url' => $entry?->team->logoUrl(128), 'image' => $image,
        ];

        return [
            'home' => $side($game->homeEntry, $game->home_score, $this->homeColor, 'home'),
            'away' => $side($game->awayEntry, $game->away_score, $this->awayColor, 'away'),
            'status' => $game->status->label(),
            'unit' => $this->unit['label'],
            'penalties' => $game->home_penalties !== null && $game->away_penalties !== null ? $game->home_penalties.' x '.$game->away_penalties : null,
            'sets' => $this->model === 'sets' && $game->sets ? implode(' · ', array_map(fn (array $set) => $set['home'].'-'.$set['away'], $game->sets)) : null,
        ];
    }

    /** @return list<array<string, mixed>|null> */
    private function overview(): array
    {
        $count = fn (array $types) => $this->events->whereIn('type', $types)->count();
        $cards = $count([GameEventType::YellowCard, GameEventType::RedCard, GameEventType::BlueCard]);
        $subs = $count([GameEventType::Substitution]);
        $fouls = $count([GameEventType::Foul, GameEventType::TechnicalFoul, GameEventType::UnsportsmanlikeFoul]);
        $timeouts = $count([GameEventType::Timeout]);
        $marks = array_merge(GameEventType::clockMarks(), [GameEventType::ShotClockReset]);
        $plays = $this->events->reject(fn (GameEvent $event) => in_array($event->type, $marks, true))->count();
        $game = $this->game;

        $score = match ($this->model) {
            'sets' => ['icon' => 'target', 'label' => 'Sets disputados', 'value' => (string) count($game->sets ?? [])],
            default => ['icon' => $this->model === 'points' ? 'target' : 'ball', 'label' => ucfirst($this->unit['plural']).' na partida', 'value' => (string) ($game->home_score + $game->away_score)],
        };

        $kpis = array_values(array_filter([
            $score,
            $this->model === 'sets' ? ['icon' => 'bolt', 'label' => 'Pontos de rally', 'value' => (string) array_sum(array_map(fn (array $set) => $set['home'] + $set['away'], $game->sets ?? []))] : null,
            $this->cards ? ['icon' => 'card', 'label' => 'Cartões', 'value' => (string) $cards, 'color' => '#eab308'] : null,
            ['icon' => 'swap', 'label' => 'Substituições', 'value' => (string) $subs, 'color' => '#0ea5e9'],
            $fouls > 0 || $game->sport()->profile()->teamFoulLimit !== null ? ['icon' => 'whistle', 'label' => 'Faltas', 'value' => (string) $fouls, 'color' => '#f97316'] : null,
            ['icon' => 'list', 'label' => 'Lances na súmula', 'value' => (string) $plays],
            $game->started_at !== null && $game->finished_at !== null && $game->started_at->diffInMinutes($game->finished_at) >= 1 ? ['icon' => 'clock', 'label' => 'Duração', 'value' => (int) $game->started_at->diffInMinutes($game->finished_at).' min'] : null,
        ]));

        $scoring = match ($this->model) {
            'points' => $count(GameEventType::scoring()),
            'sets' => 0,
            default => $count([GameEventType::Goal, GameEventType::OwnGoal]),
        };

        return [
            ReportKit::kpis($kpis),
            $plays > 0 ? ReportKit::donut('Lances por tipo', [
                ['label' => $this->model === 'points' ? 'Cestas' : 'Gols', 'value' => $scoring, 'color' => ReportKit::WIN],
                ['label' => 'Cartões', 'value' => $this->cards ? $cards : 0, 'color' => '#eab308'],
                ['label' => 'Substituições', 'value' => $subs, 'color' => '#0ea5e9'],
                ['label' => 'Faltas', 'value' => $fouls, 'color' => '#f97316'],
                ['label' => 'Pedidos de tempo', 'value' => $timeouts, 'color' => '#8b5cf6'],
            ], 'lances') : null,
        ];
    }

    /** @return list<array<string, mixed>|null> */
    private function comparison(): array
    {
        $by = function (array $types, ?callable $value = null): array {
            $out = ['home' => 0, 'away' => 0];

            foreach ($this->events->whereIn('type', $types) as $event) {
                $side = $this->side($event);

                if ($side !== null) {
                    $out[$side] += $value ? $value($event) : 1;
                }
            }

            return $out;
        };
        $game = $this->game;
        $rows = [['label' => $this->unit['label'], 'home' => $game->home_score, 'away' => $game->away_score]];

        if ($this->model === 'sets') {
            $rows[] = ['label' => 'Pontos de rally', 'home' => array_sum(array_column($game->sets ?? [], 'home')), 'away' => array_sum(array_column($game->sets ?? [], 'away'))];
        }

        $optional = [];

        if ($this->model === 'points') {
            $optional['Cestas de 3'] = $by([GameEventType::ThreePointer]);
            $optional['Cestas de 2'] = $by([GameEventType::FieldGoal]);
            $optional['Lances livres'] = $by([GameEventType::FreeThrow]);
        }

        if ($this->cards) {
            $optional['Cartões amarelos'] = $by([GameEventType::YellowCard]);
            $optional['Cartões vermelhos'] = $by([GameEventType::RedCard]);
            $optional['Cartões azuis'] = $by([GameEventType::BlueCard]);
        }

        $optional['Faltas'] = $by([GameEventType::Foul, GameEventType::TechnicalFoul, GameEventType::UnsportsmanlikeFoul]);
        $optional['Substituições'] = $by([GameEventType::Substitution]);
        $optional['Pedidos de tempo'] = $by([GameEventType::Timeout]);

        foreach ($optional as $label => $values) {
            if ($values['home'] + $values['away'] > 0) {
                $rows[] = ['label' => $label, 'home' => $values['home'], 'away' => $values['away']];
            }
        }

        return [ReportKit::versus(['name' => ReportKit::team($game->homeEntry), 'color' => $this->homeColor], ['name' => ReportKit::team($game->awayEntry), 'color' => $this->awayColor], $rows)];
    }

    /** @return list<array<string, mixed>|null> */
    private function periods(): array
    {
        $game = $this->game;
        $byPeriod = [];

        if ($this->model === 'sets') {
            foreach ($game->sets ?? [] as $index => $set) {
                $byPeriod[$index + 1] = ['home' => (int) $set['home'], 'away' => (int) $set['away']];
            }
        } else {
            $types = $this->model === 'points' ? GameEventType::scoring() : [GameEventType::Goal, GameEventType::OwnGoal];

            foreach ($this->events->whereIn('type', $types) as $event) {
                $side = $this->side($event);

                if ($side === null) {
                    continue;
                }

                if ($event->type === GameEventType::OwnGoal) {
                    $side = $side === 'home' ? 'away' : 'home';
                }

                $period = max(1, (int) $event->period);
                $byPeriod[$period] ??= ['home' => 0, 'away' => 0];
                $byPeriod[$period][$side] += $this->model === 'points' ? (int) (($event->payload ?? [])['points'] ?? $event->type->pointValue() ?? 0) : 1;
            }

            // períodos já jogados sem placar também aparecem (um 0 x 0 no 1º tempo é informação)
            foreach ($this->events->where('type', GameEventType::PeriodStart) as $event) {
                $byPeriod[max(1, (int) $event->period)] ??= ['home' => 0, 'away' => 0];
            }
        }

        ksort($byPeriod);

        if ($byPeriod === []) {
            return [ReportKit::note('O placar por período aparece quando a súmula registra o início dos períodos ou os primeiros '.$this->unit['plural'].'.')];
        }

        $series = [['label' => ReportKit::short($game->homeEntry), 'color' => $this->homeColor], ['label' => ReportKit::short($game->awayEntry), 'color' => $this->awayColor]];
        $categories = array_map(fn (int $period, array $score) => ['label' => self::periodLabel($this->model, $period), 'values' => [$score['home'], $score['away']]], array_keys($byPeriod), $byPeriod);

        return [ReportKit::columns(ucfirst($this->unit['plural']).' por '.($this->model === 'sets' ? 'set (pontos)' : 'período'), $series, $categories, $this->model === 'sets' ? 'pontos' : $this->unit['plural'])];
    }

    /** @return list<array<string, mixed>|null> */
    private function timeline(): array
    {
        $items = [];

        foreach ($this->events as $event) {
            $item = $this->timelineItem($event);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        $game = $this->game;

        if ($game->home_penalties !== null && $game->away_penalties !== null) {
            $items[] = ['minute' => null, 'period' => 'Pênaltis', 'side' => null, 'icon' => 'target', 'color' => '#64748b', 'title' => 'Disputa de pênaltis', 'detail' => ReportKit::short($game->homeEntry).' '.$game->home_penalties.' x '.$game->away_penalties.' '.ReportKit::short($game->awayEntry)];
        }

        return [$items === [] ? ReportKit::note('Nenhum lance registrado na súmula.') : ReportKit::timeline($items)];
    }

    /** @return array<string, mixed>|null */
    private function timelineItem(GameEvent $event): ?array
    {
        $type = $event->type;
        $person = ReportKit::name($event->person, $this->public);
        $minute = $event->minute !== null ? $event->minute."'" : null;
        $period = self::periodLabel($this->model, max(1, (int) $event->period));
        $side = $this->side($event);
        $item = fn (string $icon, string $color, string $title, ?string $detail) => ['minute' => $minute, 'period' => $period, 'side' => $side, 'icon' => $icon, 'color' => $color, 'title' => $title, 'detail' => $detail];

        return match (true) {
            $type === GameEventType::PeriodStart => ['minute' => null, 'period' => $period, 'side' => null, 'icon' => 'whistle', 'color' => '#64748b', 'title' => 'Início · '.$period, 'detail' => null],
            $type === GameEventType::PeriodEnd => ['minute' => null, 'period' => $period, 'side' => null, 'icon' => 'whistle', 'color' => '#64748b', 'title' => 'Fim · '.$period, 'detail' => null],
            $type === GameEventType::Goal && $this->model === 'goals' => $item('ball', ReportKit::WIN, 'Gol', $person),
            $type === GameEventType::OwnGoal => $item('ball', '#64748b', 'Gol contra', $person),
            $type === GameEventType::Substitution => $item('swap', '#0ea5e9', 'Substituição', trim('Entra '.(ReportKit::name($event->secondaryPerson, $this->public) ?? '—').' · sai '.($person ?? '—'))),
            $type === GameEventType::Timeout => $item('clock', '#8b5cf6', 'Pedido de tempo', null),
            ! $this->cards => null,
            $type === GameEventType::YellowCard => $item('card', '#eab308', 'Cartão amarelo', $person),
            $type === GameEventType::RedCard => $item('card', '#dc2626', 'Cartão vermelho', $person),
            $type === GameEventType::BlueCard => $item('card', '#2563eb', 'Cartão azul', $person),
            $type === GameEventType::Warning => $item('card', '#f97316', 'Advertência', $person),
            $type === GameEventType::TechnicalFoul => $item('whistle', '#f97316', 'Falta técnica', $person),
            $type === GameEventType::UnsportsmanlikeFoul => $item('whistle', '#dc2626', 'Falta antidesportiva', $person),
            default => null,
        };
    }

    /** @return list<array<string, mixed>|null> */
    private function lineups(): array
    {
        $game = $this->game;
        $blocks = [];

        foreach ([$game->homeEntry, $game->awayEntry] as $entry) {
            if ($entry === null) {
                continue;
            }

            $rows = GameLineup::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->with('person')->get();
            $roles = RosterEntry::query()->where('entry_id', $entry->getKey())->whereIn('person_id', $rows->pluck('person_id')->filter()->unique()->values())->pluck('role', 'person_id');
            $tactic = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $entry->getKey())->first();
            $formation = $tactic?->formation !== null ? Formations::find($game->sport()->profile()->key, $tactic->formation) : null;

            $players = $rows->whereIn('slot', [LineupSlot::Starter, LineupSlot::Bench])
                ->sortBy(fn (GameLineup $row) => [$row->slot === LineupSlot::Starter ? 0 : 1, $row->shirt_number ?? 999])
                ->map(fn (GameLineup $row) => ['cells' => [
                    'shirt' => $row->shirt_number,
                    'name' => (ReportKit::name($row->person, $this->public) ?? '—').($row->is_captain ? ' (C)' : ''),
                    'position' => $formation?->slot((string) $row->position)?->abbr,
                    'slot' => $row->slot === LineupSlot::Starter ? 'Titular' : 'Reserva',
                ], 'highlight' => $row->slot === LineupSlot::Starter])->values()->all();

            if ($players === []) {
                $blocks[] = ReportKit::note(ReportKit::team($entry).': escalação ainda não lançada.');

                continue;
            }

            $title = ReportKit::team($entry).($formation !== null ? ' · '.$formation->label : '');
            $blocks[] = ReportKit::table($title, [
                ['key' => 'shirt', 'label' => 'Nº', 'align' => 'center', 'width' => 1],
                ['key' => 'name', 'label' => 'Atleta', 'width' => 6],
                ['key' => 'position', 'label' => 'Posição', 'align' => 'center', 'width' => 2],
                ['key' => 'slot', 'label' => 'Situação', 'align' => 'center', 'width' => 2],
            ], $players);

            // Comissão: no relatório interno vai toda; no público, só o técnico (como a camada de escalação).
            $staff = $rows->where('slot', LineupSlot::Staff)
                ->filter(fn (GameLineup $row) => ! $this->public || $roles->get($row->person_id) === RosterRole::Coach)
                ->map(fn (GameLineup $row) => ($roles->get($row->person_id)?->label() ?? 'Comissão').': '.(ReportKit::name($row->person, $this->public) ?? '—'))
                ->values()->all();

            if ($staff !== []) {
                $blocks[] = ReportKit::note(implode(' · ', $staff));
            }
        }

        return $blocks;
    }

    /** @return list<array<string, mixed>|null> */
    private function standings(): array
    {
        $game = $this->game;
        $calculator = app(CalculateStandings::class);
        $entries = match (true) {
            $game->group !== null => $calculator->forGroup($game->group),
            $game->stage->type === StageType::League => $calculator->forStage($game->stage),
            default => null,
        };

        if ($entries === null) {
            return [ReportKit::note('Jogo de mata-mata: a fase não tem tabela de classificação.')];
        }

        return [ChampionshipReport::standingsTable((string) (data_get($game, 'group.name') ?? $game->stage->name), $entries, $this->model, [$game->home_entry_id, $game->away_entry_id])];
    }

    /** @return list<array<string, mixed>|null> */
    private function form(): array
    {
        $game = $this->game;

        if ($game->homeEntry === null || $game->awayEntry === null) {
            return [];
        }

        $recent = function (Entry $entry) use ($game): array {
            return Game::query()
                ->where('division_id', $game->division_id)
                ->whereKeyNot($game->getKey())
                ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
                ->where(fn ($query) => $query->where('home_entry_id', $entry->getKey())->orWhere('away_entry_id', $entry->getKey()))
                ->when($game->scheduled_at !== null, fn ($query) => $query->where('scheduled_at', '<', $game->scheduled_at))
                ->orderByDesc('scheduled_at')->orderByDesc('id')->limit(5)->get()
                ->reverse()->map(fn (Game $past) => ReportKit::result($past, (string) $entry->getKey()))->values()->all();
        };

        $blocks = [ReportKit::form('Últimos jogos na divisão', [
            ['label' => ReportKit::team($game->homeEntry), 'color' => $this->homeColor, 'results' => $recent($game->homeEntry)],
            ['label' => ReportKit::team($game->awayEntry), 'color' => $this->awayColor, 'results' => $recent($game->awayEntry)],
        ])];

        $homeTeam = $game->homeEntry->team_id;
        $awayTeam = $game->awayEntry->team_id;
        $teamOf = Entry::query()->whereIn('team_id', [$homeTeam, $awayTeam])->pluck('team_id', 'id');
        $meetings = Game::query()
            ->whereKeyNot($game->getKey())
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->whereIn('home_entry_id', $teamOf->keys())->whereIn('away_entry_id', $teamOf->keys())
            ->with(['homeEntry.team', 'awayEntry.team', 'division.season.competition'])
            ->orderByDesc('scheduled_at')->orderByDesc('id')->get()
            ->filter(fn (Game $past) => $teamOf[$past->home_entry_id] !== $teamOf[$past->away_entry_id])
            ->values();

        if ($meetings->isEmpty()) {
            $blocks[] = ReportKit::note('Primeiro confronto entre as equipes registrado na plataforma.');

            return $blocks;
        }

        $wins = ['home' => 0, 'draw' => 0, 'away' => 0];

        foreach ($meetings as $past) {
            $winner = $past->home_score === $past->away_score ? null : ($past->home_score > $past->away_score ? $past->home_entry_id : $past->away_entry_id);
            $wins[$winner === null ? 'draw' : ($teamOf[$winner] === $homeTeam ? 'home' : 'away')]++;
        }

        $blocks[] = ReportKit::kpis([
            ['icon' => 'home', 'label' => 'Vitórias · '.ReportKit::short($game->homeEntry), 'value' => (string) $wins['home'], 'color' => $this->homeColor],
            ['icon' => 'equal', 'label' => 'Empates', 'value' => (string) $wins['draw'], 'color' => ReportKit::DRAW],
            ['icon' => 'away', 'label' => 'Vitórias · '.ReportKit::short($game->awayEntry), 'value' => (string) $wins['away'], 'color' => $this->awayColor],
        ]);
        $blocks[] = ReportKit::list('Confrontos anteriores', $meetings->take(5)->map(fn (Game $past) => [
            'title' => ReportKit::team($past->homeEntry).' x '.ReportKit::team($past->awayEntry),
            'detail' => $past->division->season->competition->name.' · '.($past->scheduled_at?->format('d/m/Y') ?? 'sem data'),
            'value' => $past->home_score.' x '.$past->away_score,
            'icon' => 'flag',
        ])->all());

        return $blocks;
    }

    private function side(GameEvent $event): ?string
    {
        return match ($event->entry_id) {
            null => null,
            $this->game->home_entry_id => 'home',
            $this->game->away_entry_id => 'away',
            default => null,
        };
    }

    public static function periodLabel(string $model, int $period): string
    {
        return match ($model) {
            'sets' => 'Set '.$period,
            'points' => $period <= 4 ? $period.'º quarto' : ($period - 4).'ª prorrogação',
            default => match ($period) {
                1 => '1º tempo',
                2 => '2º tempo',
                3 => '1ª prorrogação',
                4 => '2ª prorrogação',
                default => 'Período '.$period,
            },
        };
    }
}
