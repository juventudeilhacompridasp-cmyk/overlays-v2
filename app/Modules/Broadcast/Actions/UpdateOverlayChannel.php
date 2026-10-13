<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayAppearance;

final class UpdateOverlayChannel
{
    /** @param  array<string, mixed>  $data */
    public function handle(OverlayChannel $channel, array $data): OverlayChannel
    {
        if (array_key_exists('game_id', $data)) {
            $channel->game_id = is_string($data['game_id']) ? $data['game_id'] : null;
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

        $channel->save();
        event(OverlayUpdated::for($channel));

        return $channel;
    }
}
