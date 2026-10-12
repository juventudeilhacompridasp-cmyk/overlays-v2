<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lança o resultado da disputa de pênaltis de um jogo de mata-mata (só o total convertido por equipe).
 * Cada pênalti convertido vira um evento `penalty_kick` anônimo; um lançamento anterior é anulado por
 * eventos compensatórios. Com `null` nos dois lados, apenas remove a disputa registrada.
 */
final class RecordShootout
{
    public function __construct(private readonly ProjectGameScore $projectScore = new ProjectGameScore) {}

    public function handle(Game $game, ?int $home, ?int $away, ?int $recordedBy = null): Game
    {
        if ($game->stage->type !== StageType::Knockout) {
            throw new InvalidArgumentException('Só jogos de mata-mata têm disputa de pênaltis.');
        }

        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if ($game->status->isDecided()) {
            throw new InvalidArgumentException('O jogo já foi encerrado. Reabra para alterar os pênaltis.');
        }

        if (($home === null) !== ($away === null)) {
            throw new InvalidArgumentException('Informe os pênaltis das duas equipes.');
        }

        if ($home !== null && $away !== null) {
            if ($home < 0 || $away < 0 || $home > 30 || $away > 30) {
                throw new InvalidArgumentException('Pênaltis convertidos devem ficar entre 0 e 30.');
            }

            if ($home === $away) {
                throw new InvalidArgumentException('A disputa de pênaltis não pode terminar empatada.');
            }
        }

        return DB::transaction(function () use ($game, $home, $away, $recordedBy): Game {
            foreach ($game->events()->effective()->where('type', GameEventType::PenaltyKick)->get() as $previous) {
                $this->event($game, $previous->entry_id, $recordedBy, $previous->payload, $previous->getKey());
            }

            foreach ([[$game->home_entry_id, $home], [$game->away_entry_id, $away]] as [$entryId, $count]) {
                for ($i = 0; $i < (int) $count; $i++) {
                    $this->event($game, $entryId, $recordedBy, ['shootout' => true, 'scored' => true]);
                }
            }

            // Sem eventos de pênalti o placar de pênaltis volta a nulo; a projeção já cuida disso.
            return $this->projectScore->handle($game);
        });
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function event(Game $game, ?string $entryId, ?int $recordedBy, ?array $payload, ?string $compensates = null): void
    {
        GameEvent::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'type' => GameEventType::PenaltyKick,
            'period' => 1,
            'entry_id' => $entryId,
            'payload' => $payload,
            'compensates_event_id' => $compensates,
            'recorded_by' => $recordedBy,
        ]);
    }
}
