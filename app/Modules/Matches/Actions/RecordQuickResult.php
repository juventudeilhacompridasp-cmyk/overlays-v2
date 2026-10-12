<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lança só o placar final (sem súmula detalhada): registra um gol anônimo por gol, marcado como `quick`,
 * e encerra o jogo. Se já havia um resultado rápido, ele é anulado por eventos compensatórios antes de
 * lançar o novo. Jogos com gols detalhados (súmula) não podem ser alterados por aqui.
 */
final class RecordQuickResult
{
    public function __construct(private readonly FinishGame $finish = new FinishGame) {}

    public function handle(Game $game, int $homeGoals, int $awayGoals, ?int $recordedBy = null): Game
    {
        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if ($game->status->isDecided()) {
            throw new InvalidArgumentException('O jogo já está encerrado. Reabra para lançar de novo.');
        }

        if ($homeGoals < 0 || $awayGoals < 0) {
            throw new InvalidArgumentException('O placar não pode ser negativo.');
        }

        return DB::transaction(function () use ($game, $homeGoals, $awayGoals, $recordedBy): Game {
            $goalTypes = [GameEventType::Goal, GameEventType::OwnGoal];

            $effective = $game->events()->effective()->whereIn('type', $goalTypes)->get();

            if ($effective->contains(fn (GameEvent $event) => ($event->payload['quick'] ?? false) !== true)) {
                throw new InvalidArgumentException('Este jogo tem gols lançados na súmula; edite pela súmula.');
            }

            foreach ($effective as $previous) {
                $this->event($game, $previous->type, $previous->entry_id, $recordedBy, ['quick' => true], $previous->getKey());
            }

            foreach ([[$game->home_entry_id, $homeGoals], [$game->away_entry_id, $awayGoals]] as [$entryId, $goals]) {
                for ($i = 0; $i < $goals; $i++) {
                    $this->event($game, GameEventType::Goal, $entryId, $recordedBy, ['quick' => true]);
                }
            }

            return $this->finish->handle($game);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(Game $game, GameEventType $type, ?string $entryId, ?int $recordedBy, array $payload, ?string $compensates = null): void
    {
        GameEvent::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'type' => $type,
            'period' => 1,
            'entry_id' => $entryId,
            'payload' => $payload,
            'compensates_event_id' => $compensates,
            'recorded_by' => $recordedBy,
        ]);
    }
}
