<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use InvalidArgumentException;

/**
 * Reabre um jogo encerrado (ou W.O.) para corrigir o resultado: ele sai da classificação até ser encerrado de novo.
 * Os eventos não são apagados (continuam como histórico).
 */
final class ReopenGame
{
    public function __construct(private readonly KnockoutProgress $knockout = new KnockoutProgress) {}

    public function handle(Game $game): Game
    {
        if (! $game->status->isDecided()) {
            throw new InvalidArgumentException('Só jogos encerrados ou com W.O. podem ser reabertos.');
        }

        $this->knockout->assertCanRetract($game);

        $wasWalkover = $game->status === GameStatus::Walkover;

        $game->forceFill([
            'status' => GameStatus::Scheduled,
            'finished_at' => null,
            'home_penalties' => null,
            'away_penalties' => null,
            ...($wasWalkover ? ['home_score' => 0, 'away_score' => 0] : []),
        ])->save();

        $this->knockout->sync($game);

        return $game;
    }
}
