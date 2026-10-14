<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Portal\Support\PublicName;
use App\Modules\Rules\Sheet\ClockMark;
use App\Modules\Rules\Sheet\ShotClock;
use App\Modules\Teams\Models\Person;

/**
 * Retrato PÚBLICO do jogo (placar, relógio e gols), usado no snapshot da página ao vivo e nos deltas do Reverb.
 * LGPD: cartões, disciplina, escalação e dados pessoais NÃO entram aqui; do autor do gol só o apelido/nome.
 */
final class PublicGameSnapshot
{
    /** @return array<string, mixed> */
    public static function for(Game $game): array
    {
        $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'venue', 'stage', 'division.season.competition']);

        $goals = $game->events()->effective()
            ->whereIn('type', [GameEventType::Goal, GameEventType::OwnGoal])
            ->orderBy('id')
            ->get();

        $names = Person::query()
            ->whereIn('id', $goals->pluck('person_id')->filter()->unique())
            ->get(['id', 'name', 'nickname', 'birth_date'])
            ->mapWithKeys(fn (Person $person) => [$person->id => PublicName::of($person)]);

        $marks = ChangeGamePeriod::marks($game);

        $side = fn (?string $entryId): ?string => $entryId === null ? null : ($entryId === $game->home_entry_id ? 'home' : ($entryId === $game->away_entry_id ? 'away' : null));
        $team = fn ($entry): ?array => $entry === null ? null : ['name' => $entry->team->name, 'short_name' => $entry->team->short_name, 'logo_url' => $entry->team->logoUrl(512), 'color' => $entry->team->primary_color];

        return [
            'id' => $game->getKey(),
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'finished' => $game->status->isDecided(),
            'home' => $team($game->homeEntry),
            'away' => $team($game->awayEntry),
            'home_score' => $game->home_score,
            'away_score' => $game->away_score,
            'home_penalties' => $game->home_penalties,
            'away_penalties' => $game->away_penalties,
            'scheduled_at' => $game->scheduled_at?->toIso8601String(),
            'venue' => $game->venue?->name,
            'competition' => $game->division->season->competition->name,
            'division' => $game->division->name,
            'stage' => $game->stage->name,
            'sport' => $game->sport()->value,
            'score_model' => $game->sport()->profile()->scoreModel,
            'sets' => $game->sport()->profile()->scoreModel === 'sets' ? ($game->sets ?? []) : null,
            'clock' => $game->clock()->snapshot($marks) + self::clockDisplay($game, $marks),
            'shot_clock' => self::shotClock($game),
            'quarters' => self::quarters($game),
            'fouls' => self::fouls($game, $marks, $side),
            'goals' => $goals->map(function (GameEvent $event) use ($side, $names) {
                $own = $event->type === GameEventType::OwnGoal;
                $scoringSide = $side($event->entry_id);

                return [
                    // Gol contra conta para o adversário de quem o fez.
                    'side' => $own ? ($scoringSide === 'home' ? 'away' : 'home') : $scoringSide,
                    'player' => $event->person_id ? ($names[$event->person_id] ?? null) : null,
                    'minute' => $event->minute,
                    'period' => (int) $event->period,
                    'own_goal' => $own,
                ];
            })->values()->all(),
            'server_time_ms' => (int) (microtime(true) * 1000),
        ];
    }

    /**
     * Faltas acumuladas por equipe no tempo em andamento (null se o esporte não conta faltas).
     * Só números: quem cometeu a falta não é divulgado.
     *
     * @param  list<ClockMark>  $marks
     * @return array{home: int, away: int, limit: int, period: int}|null
     */
    private static function fouls(Game $game, array $marks, \Closure $side): ?array
    {
        $profile = $game->sport()->profile();

        if ($profile->teamFoulLimit === null) {
            return null;
        }

        $current = $game->clock()->snapshot($marks)['period'] ?? 1;
        $group = $profile->foulGroup((int) $current);
        $counts = ['home' => 0, 'away' => 0];

        $game->events()->effective()->whereIn('type', GameEventType::personalFouls())->get()->each(function (GameEvent $event) use (&$counts, $side, $profile, $group): void {
            $team = $side($event->entry_id);

            if ($team !== null && $profile->foulGroup((int) $event->period) === $group) {
                $counts[$team]++;
            }
        });

        return ['home' => $counts['home'], 'away' => $counts['away'], 'limit' => $profile->teamFoulLimit, 'period' => $group];
    }

    /** @return array{state: string, period: int|null, base_seconds: int, running_since_ms: int|null}|null */
    private static function shotClock(Game $game): ?array
    {
        if ($game->sport()->profile()->scoreModel !== 'points') {
            return null;
        }

        $types = [...GameEventType::clockMarks(), GameEventType::ShotClockReset];
        $events = $game->events()->effective()->whereIn('type', $types)->orderBy('id')->get()->map(fn (GameEvent $event) => [
            'type' => $event->type->value,
            'period' => (int) $event->period,
            'at_ms' => $event->created_at->getTimestampMs(),
            'seconds' => (int) ($event->payload['seconds'] ?? 24),
        ])->all();

        return (new ShotClock)->snapshot($events);
    }

    /**
     * Como mostrar o relógio quando ele é regressivo (basquete): o cliente calcula o tempo restante do período.
     * Esportes de relógio corrido não acrescentam nada.
     *
     * @param  list<ClockMark>  $marks
     * @return array<string, mixed>
     */
    private static function clockDisplay(Game $game, array $marks): array
    {
        $profile = $game->sport()->profile();

        if ($profile->clockDirection !== 'down') {
            return [];
        }

        $clock = $game->clock();
        $period = $clock->snapshot($marks)['period'] ?? 1;

        return [
            'direction' => 'down',
            'pausable' => $profile->pausableClock,
            'offset_seconds' => $clock->offsetSeconds($period),
            'period_seconds' => $clock->periodMinutes($period) * 60,
        ];
    }

    /**
     * Pontos por período (esportes de pontos); null nos demais.
     *
     * @return list<array{period: int, home: int, away: int}>|null
     */
    private static function quarters(Game $game): ?array
    {
        if ($game->sport()->profile()->scoreModel !== 'points') {
            return null;
        }

        $byPeriod = [];

        foreach ($game->events()->effective()->whereIn('type', GameEventType::scoring())->get() as $event) {
            $side = $event->entry_id === $game->home_entry_id ? 'home' : ($event->entry_id === $game->away_entry_id ? 'away' : null);

            if ($side === null) {
                continue;
            }

            $period = (int) $event->period;
            $byPeriod[$period] ??= ['period' => $period, 'home' => 0, 'away' => 0];
            $byPeriod[$period][$side] += (int) ($event->payload['points'] ?? $event->type->pointValue() ?? 0);
        }

        ksort($byPeriod);

        return array_values($byPeriod);
    }
}
