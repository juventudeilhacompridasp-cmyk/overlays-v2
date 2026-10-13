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
            // Vôlei: placar em sets (melhor de 5; 25 pontos, decisivo de 15; vantagem mínima de 2), sem relógio.
            // Tabela: 3-0/3-1 = 3 pontos; 3-2 = 2 para o vencedor e 1 para o perdedor. W.O. = 3-0 em sets.
            'volleyball' => new SportProfile('volleyball', 'Vôlei', [], [
                'points' => ['win' => 3, 'draw' => 0, 'loss' => 0],
                'tiebreakers' => ['wins', 'set_ratio', 'point_ratio', 'head_to_head', 'draw'],
                'scoring' => ['model' => 'sets', 'sets_to_win' => 3, 'set_points' => 25, 'deciding_set_points' => 15, 'min_lead' => 2, 'points_narrow_win' => 2, 'points_narrow_loss' => 1],
                'walkover' => ['winner_goals' => 3, 'loser_goals' => 0],
                'substitutions' => ['max' => 99, 'max_windows' => 99, 'allow_reentry' => true],
                'lineup' => ['max_starters' => 6, 'max_bench' => 6, 'max_staff' => 3],
            ], scoreModel: 'sets'),
            default => throw new InvalidArgumentException("Esporte sem perfil: {$sport}."),
        };
    }
}
