<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayLineupTimeline;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;
use App\Modules\Broadcast\Support\OverlayState;

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
        $lineupBefore = OverlayLineupSettings::normalize($channel->lineup_settings);
        $lineupWasOn = $channel->layerOn('lineup');

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

        if (isset($data['championship_live']) && is_array($data['championship_live'])) {
            $channel->layers = array_merge($channel->layers ?? [], [
                'championship' => array_merge(
                    $channel->championshipLive(),
                    array_intersect_key(array_map('boolval', $data['championship_live']), array_flip(OverlayChannel::CHAMPIONSHIP_VIEWS)),
                ),
            ]);
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

        // Mudou o jogo, a equipe, o modo ou os tempos (ou a escalação acabou de entrar no ar): a apresentação recomeça da etapa 0.
        $lineup = OverlayLineupSettings::normalize($channel->lineup_settings);
        $watched = ['presentation', 'team', 'individual_mode', 'individual_scope', 'list_seconds', 'individual_seconds'];
        $restart = $gameChanged || (! $lineupWasOn && $channel->layerOn('lineup')) || array_diff_assoc(array_intersect_key($lineup, array_flip($watched)), array_intersect_key($lineupBefore, array_flip($watched))) !== [];

        if ($restart) {
            $channel->lineup_settings = array_merge($lineup, ['step' => 0, 'anchor_ms' => (int) (microtime(true) * 1000)]);
        }

        // Comando do operador (próximo, anterior, reiniciar): vale na hora e é calculado aqui para duas pessoas não se atropelarem.
        if (isset($data['lineup_command']) && is_string($data['lineup_command'])) {
            $channel->lineup_settings = array_merge(
                OverlayLineupSettings::normalize($channel->lineup_settings),
                OverlayLineupTimeline::apply($data['lineup_command'], OverlayLineupSettings::normalize($channel->lineup_settings), OverlayState::individualCount($channel), (int) (microtime(true) * 1000)),
            );
        }

        if (isset($data['sponsor_settings']) && is_array($data['sponsor_settings'])) {
            $channel->sponsor_settings = OverlaySponsorSettings::normalize(array_merge($channel->sponsor_settings ?? [], $data['sponsor_settings']));
        }

        $channel->save();
        OverlayUpdated::dispatchFor($channel);

        return $channel;
    }
}
