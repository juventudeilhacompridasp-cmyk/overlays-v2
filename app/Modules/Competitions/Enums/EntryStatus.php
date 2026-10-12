<?php

namespace App\Modules\Competitions\Enums;

enum EntryStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';
    case Excluded = 'excluded';
}
