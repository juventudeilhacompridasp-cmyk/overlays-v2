<?php

namespace App\Modules\Competitions\Enums;

use App\Modules\Rules\Sports\SportProfile;
use App\Modules\Rules\Sports\SportProfiles;

enum Sport: string
{
    case Football = 'football';
    case Futsal = 'futsal';
    case Volleyball = 'volleyball';

    public function profile(): SportProfile
    {
        return SportProfiles::for($this->value);
    }

    public function label(): string
    {
        return $this->profile()->label;
    }
}
