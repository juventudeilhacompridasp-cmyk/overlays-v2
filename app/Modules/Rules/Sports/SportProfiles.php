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
            // Basquete (FIBA): 4 quartos de 10 min + prorrogações de 5 min, relógio regressivo que para e volta,
            // pontos de 1/2/3, 5 faltas por atleta (eliminado) e 5 faltas de equipe por quarto (prorrogação segue o 4º).
            // Pedidos de tempo por equipe: 2 na 1ª metade (quartos 1-2), 3 na 2ª (quartos 3-4) e 1 por prorrogação.
            // Tabela: vitória 2, derrota 1, derrota por W.O. 0 (W.O. = 20-0); sem empate (prorrogação até desempatar).
            'basketball' => new SportProfile('basketball', 'Basquete', [10, 10, 10, 10, 5, 5, 5, 5], [
                'points' => ['win' => 2, 'draw' => 0, 'loss' => 1, 'walkover_loss' => 0],
                'tiebreakers' => ['head_to_head', 'goal_difference', 'goals_for', 'draw'],
                'scoring' => ['model' => 'points'],
                'walkover' => ['winner_goals' => 20, 'loser_goals' => 0],
                'substitutions' => ['max' => 99, 'max_windows' => 99, 'allow_reentry' => true],
                'lineup' => ['max_starters' => 5, 'max_bench' => 7, 'max_staff' => 3],
                'fouls' => ['max_player' => 5],
            ], teamFoulLimit: 5, scoreModel: 'points', clockDirection: 'down', pausableClock: true, foulGroups: [1, 2, 3, 4], timeoutGroups: [1, 1, 2, 2, 5, 6, 7, 8], timeoutLimits: [1 => 2, 2 => 3, 5 => 1, 6 => 1, 7 => 1, 8 => 1]),
            default => throw new InvalidArgumentException("Esporte sem perfil: {$sport}."),
        };
    }
}
