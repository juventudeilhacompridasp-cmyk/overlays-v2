<?php

namespace App\Modules\Matches\Scheduling;

/**
 * Um confronto do chaveamento. `a`/`b` são os lados (a = melhor cabeça de chave); nulos nas rodadas
 * seguintes até o vencedor da anterior ser conhecido. `bye` = confronto da 1ª rodada sem adversário
 * (a equipe já avançou e vem preenchida na rodada seguinte).
 */
final readonly class KnockoutTie
{
    public function __construct(
        public int $round,
        public int $position,
        public ?string $a,
        public ?string $b,
        public bool $bye,
        public ?int $nextPosition,
        public ?string $nextSlot,
    ) {}
}
