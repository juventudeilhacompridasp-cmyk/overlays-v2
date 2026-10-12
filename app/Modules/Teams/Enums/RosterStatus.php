<?php

namespace App\Modules\Teams\Enums;

enum RosterStatus: string
{
    case Active = 'active';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Removed => 'Removido',
        };
    }
}
