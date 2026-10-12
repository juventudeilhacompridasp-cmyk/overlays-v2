<?php

namespace App\Modules\Portal\Support;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;

/** Jogo no formato do portal público (e da API v1). Sem escalação, cartões ou dados pessoais. */
final class GamePresenter
{
    /** @return array<string, mixed> */
    public static function game(Game $game): array
    {
        return [
            'id' => $game->getKey(),
            'url' => route('public.games.show', $game->getKey()),
            'number' => $game->number,
            'round' => $game->round,
            'group' => $game->group_id !== null ? $game->group->name : null,
            'leg' => $game->leg,
            'home' => self::team($game->homeEntry),
            'away' => self::team($game->awayEntry),
            'home_score' => $game->home_score,
            'away_score' => $game->away_score,
            'home_penalties' => $game->home_penalties,
            'away_penalties' => $game->away_penalties,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'decided' => $game->status->isDecided(),
            'live' => $game->status === GameStatus::InProgress,
            'scheduled_at' => $game->scheduled_at?->format('Y-m-d\TH:i'),
            'venue' => $game->venue?->name,
        ];
    }

    /** @return array{id: string, name: string, short_name: string|null, logo_url: string|null, url: string}|null */
    public static function team(?Entry $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        $team = $entry->team;

        return [
            'id' => $team->getKey(),
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo_url' => $team->logoUrl(128),
            'url' => route('portal.team', $team->getKey()),
        ];
    }
}
