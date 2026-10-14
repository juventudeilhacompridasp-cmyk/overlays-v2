<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Teams\Models\Person;
use Illuminate\Support\Facades\Cache;

final class OverlayPhotoUrl
{
    public static function for(Person $person, int $size = 96): ?string
    {
        if (! $person->photo_path) {
            return null;
        }

        $key = 'overlay-photo:'.hash('sha256', "{$person->organization_id}|{$person->id}|{$person->photo_path}|{$size}");

        return Cache::remember($key, now()->addMinutes(4), fn () => $person->photoUrl($size, 5));
    }
}
