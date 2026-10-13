<?php

namespace App\Modules\Rules\Sheet;

/**
 * Marco do relógio com o instante (milissegundos Unix) em que foi registrado: início/fim de um período
 * (`kind` = period) ou, em esportes de relógio parável (basquete), pausa/retomada dentro do período.
 */
final readonly class ClockMark
{
    public const PERIOD = 'period';

    public const PAUSE = 'pause';

    public function __construct(
        /** Período: true = início. Pausa/retomada: true = retomada, false = pausa. */
        public bool $start,
        public int $period,
        public int $atMs = 0,
        public string $kind = self::PERIOD,
    ) {}

    public function isPause(): bool
    {
        return $this->kind === self::PAUSE;
    }
}
