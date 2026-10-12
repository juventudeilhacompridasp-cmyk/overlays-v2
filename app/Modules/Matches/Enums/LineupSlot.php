<?php

namespace App\Modules\Matches\Enums;

enum LineupSlot: string
{
    case Starter = 'starter';
    case Bench = 'bench';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Titular',
            self::Bench => 'Reserva',
            self::Staff => 'Comissão',
        };
    }
}
