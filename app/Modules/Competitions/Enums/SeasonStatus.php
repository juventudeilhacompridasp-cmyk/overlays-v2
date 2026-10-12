<?php

namespace App\Modules\Competitions\Enums;

enum SeasonStatus: string
{
    case Draft = 'draft';
    case Registration = 'registration';
    case Active = 'active';
    case Finished = 'finished';
}
