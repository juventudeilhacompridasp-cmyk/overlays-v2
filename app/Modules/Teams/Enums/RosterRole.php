<?php

namespace App\Modules\Teams\Enums;

enum RosterRole: string
{
    case Athlete = 'athlete';
    case Coach = 'coach';
    case AssistantCoach = 'assistant_coach';
    case Physio = 'physio';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Athlete => 'Atleta',
            self::Coach => 'Técnico',
            self::AssistantCoach => 'Auxiliar técnico',
            self::Physio => 'Massagista',
            self::Manager => 'Dirigente',
        };
    }
}
