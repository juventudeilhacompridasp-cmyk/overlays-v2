<?php

namespace App\Modules\Registration\Support;

use App\Modules\Registration\Models\Registration;
use InvalidArgumentException;

final class EnsureEditable
{
    public static function check(Registration $registration): void
    {
        if (! $registration->status->isEditable()) {
            throw new InvalidArgumentException('Esta inscrição já foi enviada e não pode mais ser alterada. Se precisar corrigir algo, fale com a organização.');
        }
    }
}
