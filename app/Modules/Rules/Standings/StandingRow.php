<?php

namespace App\Modules\Rules\Standings;

final readonly class StandingRow
{
    public function __construct(
        public string $teamId,
        public int $position,
        public int $played,
        public int $wins,
        public int $draws,
        public int $losses,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $points,
        public int $yellowCards,
        public int $redCards,
        public int $blueCards,
        /** Empate que só o sorteio resolve (todos os critérios calculáveis se esgotaram). */
        public bool $tied,
        /** Esportes de sets: pontos de rally feitos/sofridos. */
        public int $scoreFor = 0,
        public int $scoreAgainst = 0,
    ) {}

    public function goalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }

    /** Aproveitamento em % dos pontos disputados (0 se ainda não jogou). */
    public function efficiency(int $pointsPerWin = 3): float
    {
        return $this->played === 0 ? 0.0 : round($this->points / ($this->played * $pointsPerWin) * 100, 1);
    }
}
