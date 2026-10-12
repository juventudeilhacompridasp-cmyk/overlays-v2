<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use InvalidArgumentException;

/**
 * Encerra o jogo: consolida o placar a partir dos eventos válidos e marca como encerrado.
 */
final class FinishGame
{
    public function __construct(
        private readonly ProjectGameScore $projectScore = new ProjectGameScore,
        private readonly KnockoutProgress $knockout = new KnockoutProgress,
    ) {}

    public function handle(Game $game): Game
    {
        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if (in_array($game->status, [GameStatus::Finished, GameStatus::Walkover], true)) {
            throw new InvalidArgumentException('O jogo já foi encerrado.');
        }

        $this->projectScore->handle($game);
        $this->knockout->assertResolvable($game);

        $game->forceFill([
            'status' => GameStatus::Finished,
            'finished_at' => now(),
        ])->save();

        $this->knockout->sync($game);

        return $game;
    }
}
