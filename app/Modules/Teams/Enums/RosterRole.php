<?php

namespace App\Modules\Teams\Enums;

enum RosterRole: string
{
    case Athlete = 'athlete';
    case Coach = 'coach';
    case AssistantCoach = 'assistant_coach';
    case Physio = 'physio';
    case Manager = 'manager';
}
