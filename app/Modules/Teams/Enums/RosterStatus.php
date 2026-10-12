<?php

namespace App\Modules\Teams\Enums;

enum RosterStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
