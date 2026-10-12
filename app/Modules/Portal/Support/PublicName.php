<?php

namespace App\Modules\Portal\Support;

use App\Modules\Teams\Models\Person;

/**
 * Como o nome de uma pessoa aparece em páginas PÚBLICAS (LGPD). Adulto: apelido ou nome completo. Menor de idade
 * — ou idade desconhecida, por precaução — só primeiro nome e inicial do sobrenome ("João S."), sem apelido.
 */
final class PublicName
{
    public static function of(?Person $person): ?string
    {
        if ($person === null) {
            return null;
        }

        if ($person->birth_date !== null && $person->birth_date->age >= 18) {
            return $person->nickname ?: $person->name;
        }

        $parts = preg_split('/\s+/', trim($person->name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1).'.' : '';

        return trim($first.' '.$last);
    }
}
