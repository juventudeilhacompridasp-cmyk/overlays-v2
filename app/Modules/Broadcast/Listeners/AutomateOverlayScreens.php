<?php

namespace App\Modules\Broadcast\Listeners;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayAutomationSettings;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Events\GameUpdated;

/** Alterna cartelas de pré-jogo e resumo conforme o status, sem substituir uma decisão manual nas demais camadas. */
final class AutomateOverlayScreens
{
    public function handle(GameUpdated $event): void
    {
        OverlayChannel::query()->where('game_id', $event->gameId)->get()->each(function (OverlayChannel $channel) use ($event): void {
            $settings = OverlayAutomationSettings::normalize($channel->automation_settings);

            if ($settings['enabled'] === false) {
                return;
            }

            $status = (string) ($event->snapshot['status'] ?? '');
            $pregame = $settings['pregame'] && $status === GameStatus::Scheduled->value;
            $summary = $settings['summary'] && in_array($status, [GameStatus::Finished->value, GameStatus::Walkover->value], true);
            $screens = $channel->screensLive();

            if ($screens['pregame'] === $pregame && $screens['summary'] === $summary) {
                return;
            }

            $channel->layers = array_merge($channel->layers ?? [], ['screens' => array_merge($screens, ['pregame' => $pregame, 'summary' => $summary])]);
            $channel->save();
            OverlayUpdated::dispatchFor($channel);
        });
    }
}
