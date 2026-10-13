<?php

namespace App\Modules\Rules\Sports;

use InvalidArgumentException;

/** Perfis dos esportes suportados, por chave (a mesma do enum `Sport`). */
final class SportProfiles
{
    public static function for(string $sport): SportProfile
    {
        return match ($sport) {
            'football' => new SportProfile('football', 'Futebol', [45, 45, 15, 15]),
            // Futsal: 5 em quadra, banco de até 9, substituições ilimitadas e volantes (sem "atos"),
            // 2 tempos de 20 min e prorrogação de 2 x 5 min; 5 faltas acumuladas por tempo dão tiro livre direto.
            'futsal' => new SportProfile('futsal', 'Futsal', [20, 20, 5, 5], [
                'substitutions' => ['max' => 99, 'max_windows' => 99, 'allow_reentry' => true],
                'lineup' => ['max_starters' => 5, 'max_bench' => 9, 'max_staff' => 3],
            ], teamFoulLimit: 5),
            default => throw new InvalidArgumentException("Esporte sem perfil: {$sport}."),
        };
    }
}
