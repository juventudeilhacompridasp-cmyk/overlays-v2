<?php

namespace App\Modules\Broadcast\Listeners;

use App\Modules\Broadcast\Events\OverlayLowerThird;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\LowerThirdBanner;
use App\Modules\Broadcast\Support\OverlayLowerThirdSettings;
use App\Modules\Matches\Events\GameEventRecorded;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Throwable;

/**
 * Gol, cartão ou substituição lançado na súmula vira uma faixa na tela de eventos dos canais com este jogo no ar.
 * Cada canal escolhe quais lances aparecem e se o gol mostra o placar.
 */
class ShowLowerThird
{
    public function handle(GameEventRecorded $recorded): void
    {
        $channels = OverlayChannel::query()->where('game_id', $recorded->gameId)->get()->filter(fn (OverlayChannel $channel) => $channel->layerOn('lower_third'));

        if ($channels->isEmpty()) {
            return;
        }

        $event = GameEvent::query()->find($recorded->eventId);
        $game = Game::query()->with(['homeEntry.team', 'awayEntry.team'])->find($recorded->gameId);

        if ($event === null || $game === null) {
            return;
        }

        $banner = LowerThirdBanner::forEvent($event, $game);

        if ($banner === null) {
            return;
        }

        $channels->each(function (OverlayChannel $channel) use ($banner): void {
            $settings = OverlayLowerThirdSettings::normalize($channel->lower_third_settings);

            if (! in_array($banner['kind'], $settings['kinds'], true)) {
                return;
            }

            try {
                event(new OverlayLowerThird($channel->token, $settings['score'] ? $banner : [...$banner, 'score' => null]));
            } catch (Throwable $exception) {
                // O lance já está na súmula; a falta da faixa no OBS não pode desfazê-lo.
                report($exception);
            }
        });
    }
}
