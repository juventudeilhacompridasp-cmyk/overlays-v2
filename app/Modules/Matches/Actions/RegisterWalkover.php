<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use InvalidArgumentException;

/**
 * Registra W.O. a favor de uma das equipes, com o placar definido no regulamento (padrão 3 x 0).
 */
final class RegisterWalkover
{
    public function __construct(private readonly KnockoutProgress $knockout = new KnockoutProgress) {}

    public function handle(Game $game, Entry $winner): Game
    {
        if (! in_array($winner->getKey(), [$game->home_entry_id, $game->away_entry_id], true)) {
            throw new InvalidArgumentException('A equipe vencedora do W.O. precisa participar do jogo.');
        }

        if (in_array($game->status, [GameStatus::Finished, GameStatus::Walkover], true)) {
            throw new InvalidArgumentException('O jogo já foi encerrado.');
        }

        $rules = $game->division->ruleSet();
        $winnerIsHome = $winner->getKey() === $game->home_entry_id;

        $game->forceFill([
            'status' => GameStatus::Walkover,
            'home_score' => $winnerIsHome ? $rules->walkoverWinnerGoals : $rules->walkoverLoserGoals,
            'away_score' => $winnerIsHome ? $rules->walkoverLoserGoals : $rules->walkoverWinnerGoals,
            'home_penalties' => null,
            'away_penalties' => null,
            'finished_at' => now(),
        ])->save();

        $this->knockout->sync($game);

        return $game;
    }
}
