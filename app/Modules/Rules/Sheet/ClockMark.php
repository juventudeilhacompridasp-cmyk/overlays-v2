<?php

namespace App\Modules\Rules\Sheet;

/** Início ou fim de um período, com o instante (milissegundos Unix) em que foi registrado. */
final readonly class ClockMark
{
    public function __construct(
        public bool $start,
        public int $period,
        public int $atMs = 0,
    ) {}
}
