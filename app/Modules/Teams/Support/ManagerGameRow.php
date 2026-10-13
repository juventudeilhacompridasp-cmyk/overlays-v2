<?php

namespace App\Modules\Teams\Support;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Models\Game;

/** Uma linha de jogo na área do gestor: só dados públicos do jogo, do ponto de vista da inscrição dele. */
final class ManagerGameRow
{
    /** @return array<string, mixed> */
    public static function of(Game $game, Entry $mine): array
    {
        $home = $game->home_entry_id === $mine->getKey();
        $opponent = $home ? $game->awayEntry : $game->homeEntry;

        return [
            'id' => $game->getKey(),
            'home' => $home,
            'my_team' => $mine->team->name,
            'my_logo' => $mine->team->logoUrl(128),
            'opponent' => $opponent?->team->name,
            'opponent_logo' => $opponent?->team->logoUrl(128),
            'my_score' => $home ? $game->home_score : $game->away_score,
            'opponent_score' => $home ? $game->away_score : $game->home_score,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'decided' => $game->status->isDecided(),
            'scheduled_at' => $game->scheduled_at?->format('d/m/Y H:i'),
            'venue' => $game->venue?->name,
            'competition' => "{$game->division->season->competition->name} · {$game->division->name}",
            'stage' => $game->stage->name,
            'round' => $game->round,
        ];
    }
}
