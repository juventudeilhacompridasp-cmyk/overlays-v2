<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;

final class UpdateOverlayChannel
{
    /** @param  array<string, mixed>  $data */
    public function handle(OverlayChannel $channel, array $data): OverlayChannel
    {
        // Cena: aplica o que ela guarda e deixa o que veio junto (ex.: um ajuste da API) valer por cima.
        if (isset($data['scene']) && is_string($data['scene'])) {
            $scene = OverlayScene::query()->find($data['scene']);
            unset($data['scene']);

            if ($scene !== null) {
                $data = array_replace_recursive($scene->config, $data);
            }
        }

        $gameChanged = false;

        if (array_key_exists('game_id', $data)) {
            $gameId = is_string($data['game_id']) ? $data['game_id'] : null;
            $gameChanged = $channel->game_id !== $gameId;
            $channel->game_id = $gameId;
        }

        if (isset($data['layers']) && is_array($data['layers'])) {
            $channel->layers = array_merge(
                $channel->layers ?? [],
                array_intersect_key(array_map('boolval', $data['layers']), array_flip(OverlayChannel::LAYERS)),
            );
        }

        if (isset($data['appearance']) && is_array($data['appearance'])) {
            $channel->appearance = OverlayAppearance::normalize(array_merge($channel->appearance ?? [], $data['appearance']));
        }

        if ($gameChanged) {
            $channel->lineup_settings = OverlayLineupSettings::normalize(array_merge($channel->lineup_settings ?? [], ['photo_person_ids' => []]));
        }

        if (isset($data['lineup_settings']) && is_array($data['lineup_settings'])) {
            $channel->lineup_settings = OverlayLineupSettings::normalize(array_merge($channel->lineup_settings ?? [], $data['lineup_settings']));
        }

        if (isset($data['sponsor_settings']) && is_array($data['sponsor_settings'])) {
            $channel->sponsor_settings = OverlaySponsorSettings::normalize(array_merge($channel->sponsor_settings ?? [], $data['sponsor_settings']));
        }

        $channel->save();
        event(OverlayUpdated::for($channel));

        return $channel;
    }
}
