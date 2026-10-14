<?php

namespace App\Modules\Broadcast\Listeners;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Matches\Events\GameUpdated;

/** Placar, situação ou relógio do jogo mudou: atualiza os canais de transmissão que estão com este jogo no ar. */
class SyncOverlays
{
    public function handle(GameUpdated $event): void
    {
        OverlayChannel::query()->where('game_id', $event->gameId)->get()
            ->each(fn (OverlayChannel $channel) => OverlayUpdated::dispatchFor($channel));
    }
}
