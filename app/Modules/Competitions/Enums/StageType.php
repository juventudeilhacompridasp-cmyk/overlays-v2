<?php

namespace App\Modules\Competitions\Enums;

enum StageType: string
{
    case League = 'league';
    case Groups = 'groups';
    case Knockout = 'knockout';
}
