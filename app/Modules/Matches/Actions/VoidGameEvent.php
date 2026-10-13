<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Anula um lançamento da súmula com um evento compensatório (os eventos nunca são apagados) e recalcula o placar.
 * Recusa se os lançamentos seguintes deixarem de ser coerentes sem ele.
 */
final class VoidGameEvent
{
    public function __construct(
        private readonly BuildGameSheet $sheet = new BuildGameSheet,
        private readonly ProjectGameScore $projectScore = new ProjectGameScore,
    ) {}

    public function handle(Game $game, GameEvent $event, ?int $recordedBy = null): GameEvent
    {
        if ($event->game_id !== $game->getKey() || $event->compensates_event_id !== null) {
            throw new InvalidArgumentException('Lançamento inválido para este jogo.');
        }

        if ($game->status->isDecided()) {
            throw new InvalidArgumentException('O jogo já foi encerrado. Reabra para corrigir a súmula.');
        }

        if (($event->payload['quick'] ?? false) === true) {
            throw new InvalidArgumentException('Este gol faz parte de um resultado rápido; reabra o jogo para corrigir.');
        }

        $isMark = in_array($event->type, [GameEventType::PeriodStart, GameEventType::PeriodEnd], true);

        return DB::transaction(function () use ($game, $event, $recordedBy, $isMark): GameEvent {
            if ($isMark) {
                $last = $game->events()->effective()->whereIn('type', [GameEventType::PeriodStart, GameEventType::PeriodEnd])->orderByDesc('id')->first();

                if ($last?->getKey() !== $event->getKey()) {
                    throw new InvalidArgumentException('Só o último marco de período pode ser desfeito.');
                }
            }

            if ($event->type === GameEventType::Point) {
                $lastPoint = $game->events()->effective()->where('type', GameEventType::Point)->orderByDesc('id')->first();

                if ($lastPoint?->getKey() !== $event->getKey()) {
                    throw new InvalidArgumentException('Só o último ponto pode ser desfeito.');
                }
            }

            $alreadyVoided = $game->events()->where('compensates_event_id', $event->getKey())->exists();

            if ($alreadyVoided) {
                throw new InvalidArgumentException('Este lançamento já foi anulado.');
            }

            try {
                $this->sheet->handle($game, $event->getKey());
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException('Não é possível anular: os lançamentos seguintes dependem deste. '.$exception->getMessage());
            }

            $voided = GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => $event->type,
                'period' => $event->period,
                'minute' => $event->minute,
                'entry_id' => $event->entry_id,
                'person_id' => $event->person_id,
                'secondary_person_id' => $event->secondary_person_id,
                'compensates_event_id' => $event->getKey(),
                'recorded_by' => $recordedBy,
            ]);

            $this->projectScore->handle($game);

            if ($isMark || $event->type === GameEventType::Foul) {
                event(GameUpdated::for($game));
            }

            return $voided;
        });
    }
}
