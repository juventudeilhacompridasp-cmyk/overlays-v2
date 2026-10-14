<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;
use InvalidArgumentException;

/** Guarda o visual atual de um canal como cena. Aplicar uma cena é `UpdateOverlayChannel` (uma só via de mudança). */
final class SaveOverlayScene
{
    public function fromChannel(OverlayChannel $channel, string $name): OverlayScene
    {
        if (OverlayScene::query()->count() >= OverlayScene::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.OverlayScene::LIMIT.' cenas. Exclua alguma para salvar outra.');
        }

        $lineup = OverlayLineupSettings::public($channel->lineup_settings);

        return OverlayScene::query()->create([
            'name' => $name,
            'config' => [
                'appearance' => OverlayAppearance::normalize($channel->appearance),
                'layers' => array_merge(array_fill_keys(OverlayChannel::LAYERS, false), array_map('boolval', $channel->layers ?? [])),
                'sponsor_settings' => OverlaySponsorSettings::normalize($channel->sponsor_settings),
                'lineup_settings' => ['presentation' => $lineup['presentation'], 'list_seconds' => $lineup['list_seconds']],
            ],
        ]);
    }
}
