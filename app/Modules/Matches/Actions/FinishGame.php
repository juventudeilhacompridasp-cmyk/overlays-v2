<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use Illuminate\Support\Carbon;
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

    public function handle(Game $game, ?Carbon $at = null): Game
    {
        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if (in_array($game->status, [GameStatus::Finished, GameStatus::Walkover], true)) {
            throw new InvalidArgumentException('O jogo já foi encerrado.');
        }

        $this->projectScore->handle($game);

        if ($game->sport()->profile()->scoreModel === 'sets') {
            $toWin = $game->division->ruleSet()->setsToWin;

            if ($game->home_score < $toWin && $game->away_score < $toWin) {
                throw new InvalidArgumentException("A partida ainda não terminou: um lado precisa ganhar {$toWin} sets.");
            }
        }

        if ($game->sport()->profile()->scoreModel === 'points' && $game->home_score === $game->away_score) {
            throw new InvalidArgumentException('O jogo está empatado: lance a prorrogação até haver um vencedor.');
        }

        $this->knockout->assertResolvable($game);

        $game->forceFill([
            'status' => GameStatus::Finished,
            'finished_at' => $at ?? now(),
        ])->save();

        $this->knockout->sync($game);

        return $game;
    }
}
