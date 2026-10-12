<?php

namespace App\Modules\Matches\Enums;

enum OfficialRole: string
{
    case Referee = 'referee';
    case AssistantReferee1 = 'assistant_referee_1';
    case AssistantReferee2 = 'assistant_referee_2';
    case FourthOfficial = 'fourth_official';
    case Scorer = 'scorer';
}
