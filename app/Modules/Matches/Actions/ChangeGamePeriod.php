<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Rules\Sheet\ClockMark;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inicia ou encerra um período (1º tempo, 2º tempo, prorrogações). Os marcos são eventos de jogo
 * (`period_start`/`period_end`) e alimentam o cronômetro, que o cliente calcula sozinho.
 */
final class ChangeGamePeriod
{
    public function handle(Game $game, bool $start, int $period, ?int $recordedBy = null): GameEvent
    {
        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if ($game->status->isDecided() || in_array($game->status, [GameStatus::Cancelled, GameStatus::Postponed], true)) {
            throw new InvalidArgumentException('O jogo não está aberto para lançamentos.');
        }

        $event = DB::transaction(function () use ($game, $start, $period, $recordedBy): GameEvent {
            $error = $game->clock()->check(self::marks($game), new ClockMark($start, $period));

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            $event = GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => $start ? GameEventType::PeriodStart : GameEventType::PeriodEnd,
                'period' => $period,
                'recorded_by' => $recordedBy,
            ]);

            if ($game->status === GameStatus::Scheduled) {
                $game->forceFill(['status' => GameStatus::InProgress, 'started_at' => $game->started_at ?? now()])->save();
            }

            return $event;
        });

        // O relógio muda sem mudar o placar: transmite explicitamente.
        event(GameUpdated::for($game));

        return $event;
    }

    /**
     * Para ou retoma o relógio dentro do período em andamento (esportes de relógio parável, como o basquete).
     */
    public function pause(Game $game, bool $pause, ?int $recordedBy = null): GameEvent
    {
        if (! $game->sport()->profile()->pausableClock) {
            throw new InvalidArgumentException('O relógio deste esporte não para.');
        }

        if ($game->status->isDecided() || in_array($game->status, [GameStatus::Cancelled, GameStatus::Postponed], true)) {
            throw new InvalidArgumentException('O jogo não está aberto para lançamentos.');
        }

        $event = DB::transaction(function () use ($game, $pause, $recordedBy): GameEvent {
            $marks = self::marks($game);
            $period = $game->clock()->snapshot($marks)['period'];

            if ($period === null) {
                throw new InvalidArgumentException('Inicie um período antes de parar o relógio.');
            }

            $error = $game->clock()->check($marks, new ClockMark(! $pause, $period, 0, ClockMark::PAUSE));

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            return GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => $pause ? GameEventType::ClockPause : GameEventType::ClockResume,
                'period' => $period,
                'recorded_by' => $recordedBy,
            ]);
        });

        event(GameUpdated::for($game));

        return $event;
    }

    /** @return list<ClockMark> */
    public static function marks(Game $game): array
    {
        return $game->events()->effective()
            ->whereIn('type', GameEventType::clockMarks())
            ->orderBy('id')
            ->get()
            ->map(fn (GameEvent $event) => match ($event->type) {
                GameEventType::ClockPause => new ClockMark(false, (int) $event->period, (int) $event->created_at->getTimestamp() * 1000, ClockMark::PAUSE),
                GameEventType::ClockResume => new ClockMark(true, (int) $event->period, (int) $event->created_at->getTimestamp() * 1000, ClockMark::PAUSE),
                default => new ClockMark($event->type === GameEventType::PeriodStart, (int) $event->period, (int) $event->created_at->getTimestamp() * 1000),
            })
            ->all();
    }
}
