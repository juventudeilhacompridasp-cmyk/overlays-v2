<?php

namespace App\Modules\Matches\Enums;

enum GameEventType: string
{
    case Goal = 'goal';
    case OwnGoal = 'own_goal';
    case YellowCard = 'yellow_card';
    case RedCard = 'red_card';
    case BlueCard = 'blue_card';
    case Substitution = 'substitution';
    case PeriodStart = 'period_start';
    case PeriodEnd = 'period_end';
    case PenaltyKick = 'penalty_kick';
    case Warning = 'warning';
}
