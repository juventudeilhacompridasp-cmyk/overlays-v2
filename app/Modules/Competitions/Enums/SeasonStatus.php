<?php

namespace App\Modules\Competitions\Enums;

enum SeasonStatus: string
{
    case Draft = 'draft';
    case Registration = 'registration';
    case Active = 'active';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Registration => 'Inscrições abertas',
            self::Active => 'Em andamento',
            self::Finished => 'Encerrada',
        };
    }
}
