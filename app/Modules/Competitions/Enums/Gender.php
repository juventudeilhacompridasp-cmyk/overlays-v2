<?php

namespace App\Modules\Competitions\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Feminino',
            self::Mixed => 'Misto',
        };
    }
}
