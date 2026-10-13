<?php

namespace App\Modules\Portal\Push;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Jobs\SendGamePush;

/**
 * Decide quando um jogo atualizado merece um aviso push: começou, placar mudou (gol, set) ou terminou.
 * No basquete cada cesta mudaria o placar: ali só há aviso de início e fim (o placar vai por Reverb/OBS).
 */
final class GamePushNotifier
{
    public static function afterUpdate(Game $game): void
    {
        if (! PushConfig::publicKeyFor() || ! $game->wasChanged(['status', 'home_score', 'away_score'])) {
            return;
        }

        $kind = null;

        if ($game->wasChanged('status')) {
            $kind = match ($game->status) {
                GameStatus::InProgress => SendGamePush::STARTED,
                GameStatus::Finished, GameStatus::Walkover => SendGamePush::FINISHED,
                default => null,
            };
        } elseif ($game->status === GameStatus::InProgress && $game->sport()->profile()->scoreModel !== 'points') {
            $kind = SendGamePush::SCORE;
        }

        if ($kind === null) {
            return;
        }

        SendGamePush::dispatch((string) $game->organization_id, (string) $game->getKey(), $kind, route('public.games.show', $game->getKey()));
    }
}
