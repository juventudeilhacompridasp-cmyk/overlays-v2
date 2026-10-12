<?php

namespace App\Modules\Rules\Sheet;

use App\Modules\Matches\Enums\GameEventType;

/**
 * Um lançamento da súmula, reduzido ao que as regras precisam.
 */
final readonly class SheetEvent
{
    public function __construct(
        public GameEventType $type,
        public string $teamId,
        public ?string $personId = null,
        /** Substituição: quem ENTRA (personId é quem sai). */
        public ?string $secondaryPersonId = null,
        public int $period = 1,
        public ?int $minute = null,
    ) {}
}
