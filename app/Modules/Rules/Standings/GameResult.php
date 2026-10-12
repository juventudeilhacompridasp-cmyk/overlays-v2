<?php

namespace App\Modules\Rules\Standings;

use App\Modules\Rules\RuleSet;

/**
 * Resultado de um jogo encerrado, já reduzido ao que a classificação precisa.
 * No W.O. o placar é o definido pelo regulamento (use {@see self::walkover()}).
 */
final readonly class GameResult
{
    public function __construct(
        public string $homeId,
        public string $awayId,
        public int $homeGoals,
        public int $awayGoals,
        public bool $walkover = false,
    ) {}

    public static function walkover(string $winnerId, string $loserId, RuleSet $rules, bool $winnerIsHome = true): self
    {
        return $winnerIsHome
            ? new self($winnerId, $loserId, $rules->walkoverWinnerGoals, $rules->walkoverLoserGoals, true)
            : new self($loserId, $winnerId, $rules->walkoverLoserGoals, $rules->walkoverWinnerGoals, true);
    }
}
