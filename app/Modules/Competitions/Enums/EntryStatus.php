<?php

namespace App\Modules\Competitions\Enums;

enum EntryStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Confirmed => 'Confirmada',
            self::Withdrawn => 'Desistente',
            self::Excluded => 'Excluída',
        };
    }
}
