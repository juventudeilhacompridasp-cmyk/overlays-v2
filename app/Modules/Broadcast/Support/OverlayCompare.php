<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Support\GameInsights;

/**
 * Comparativo de equipes do jogo no ar: posição na tabela, forma (V/E/D) e confronto direto. Parte do {@see GameInsights}
 * (mesmos dados e interruptores do portal público): só times, placares e números; sem links, nomes de atletas ou disciplina.
 */
final class OverlayCompare
{
    /**
     * @return array{standings: array<string, mixed>|null, form: array{home: list<string>, away: list<string>}|null, head_to_head: array{home_wins: int, draws: int, away_wins: int}|null}
     */
    public static function for(Game $game): array
    {
        $insights = (new GameInsights)->for($game);
        $letters = fn (array $games): array => array_values(array_map(fn (array $item): string => (string) $item['result'], $games));

        return [
            'standings' => $insights['standings'],
            'form' => $insights['form'] === null ? null : ['home' => $letters($insights['form']['home']), 'away' => $letters($insights['form']['away'])],
            'head_to_head' => $insights['head_to_head']['summary'] ?? null,
        ];
    }
}
