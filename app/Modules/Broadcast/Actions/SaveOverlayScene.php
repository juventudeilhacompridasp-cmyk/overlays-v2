<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayLowerThirdSettings;
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
                'layers' => $channel->layerStates(),
                'sponsor_settings' => OverlaySponsorSettings::normalize($channel->sponsor_settings),
                'lower_third_settings' => OverlayLowerThirdSettings::normalize($channel->lower_third_settings),
                'lineup_settings' => array_diff_key($lineup, ['step' => 0, 'anchor_ms' => 0]),
            ],
        ]);
    }
}
