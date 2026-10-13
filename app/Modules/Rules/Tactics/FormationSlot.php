<?php

namespace App\Modules\Rules\Tactics;

/**
 * Uma vaga da formação: chave estável (`d1`, `gk`...), rótulo, sigla e posição no desenho (x/y de 0 a 100; y = 100 é o
 * fundo do campo/quadra da própria equipe). `bench` = a vaga é ocupada por um RESERVA (ex.: líbero do vôlei).
 */
final readonly class FormationSlot
{
    public function __construct(
        public string $key,
        public string $label,
        public string $abbr,
        public int $x,
        public int $y,
        public bool $bench = false,
    ) {}

    /** @return array{key: string, label: string, abbr: string, x: int, y: int, bench: bool} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'abbr' => $this->abbr, 'x' => $this->x, 'y' => $this->y, 'bench' => $this->bench];
    }
}
