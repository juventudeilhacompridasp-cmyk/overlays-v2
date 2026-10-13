<?php

namespace App\Modules\Rules\Tactics;

/**
 * Esquema tático de um esporte (código puro): conjunto de vagas que os titulares ocupam. Posição é informação
 * tática, não regra de jogo — o servidor só confere que a vaga existe e não se repete (ver `Formations`).
 */
final readonly class Formation
{
    /** @param  list<FormationSlot>  $slots */
    public function __construct(
        public string $code,
        public string $label,
        public array $slots,
    ) {}

    public function slot(string $key): ?FormationSlot
    {
        foreach ($this->slots as $slot) {
            if ($slot->key === $key) {
                return $slot;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(fn (FormationSlot $slot) => $slot->key, $this->slots);
    }

    /** @return array{code: string, label: string, slots: list<array{key: string, label: string, abbr: string, x: int, y: int, bench: bool}>} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => $this->label, 'slots' => array_map(fn (FormationSlot $slot) => $slot->toArray(), $this->slots)];
    }
}
