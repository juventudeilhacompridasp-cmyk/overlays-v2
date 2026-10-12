<?php

namespace App\Modules\Rules\Discipline;

/**
 * O que a disciplina precisa saber de um jogo de uma equipe, em ordem cronológica.
 */
final readonly class TeamGameRecord
{
    /**
     * @param  bool  $countsAsServed  Se o jogo cumpre suspensão pendente (jogo realizado ou W.O.; adiado/cancelado não cumpre).
     * @param  array<string, array{yellow?: int, red?: int}>  $cards  Cartões por pessoa neste jogo.
     */
    public function __construct(
        public string $gameId,
        public string $stageId,
        public bool $countsAsServed = true,
        public array $cards = [],
    ) {}
}
