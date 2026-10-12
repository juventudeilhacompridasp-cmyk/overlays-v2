<?php

namespace App\Modules\Matches\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Postponed = 'postponed';
    case Suspended = 'suspended';
    case Walkover = 'walkover';
    case Cancelled = 'cancelled';
}
