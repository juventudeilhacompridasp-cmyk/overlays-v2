<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayLowerThird;
use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Broadcast\Support\LowerThirdBanner;
use App\Modules\Broadcast\Support\OverlayAppearance;
use App\Modules\Broadcast\Support\OverlayAutomationSettings;
use App\Modules\Broadcast\Support\OverlayLineupSettings;
use App\Modules\Broadcast\Support\OverlayLineupTimeline;
use App\Modules\Broadcast\Support\OverlayLowerThirdSettings;
use App\Modules\Broadcast\Support\OverlayReportSettings;
use App\Modules\Broadcast\Support\OverlayScreenSettings;
use App\Modules\Broadcast\Support\OverlaySponsorSettings;
use App\Modules\Broadcast\Support\OverlayState;
use App\Modules\Broadcast\Support\OverlayStatsSettings;
use App\Modules\Matches\Models\Game;
use Throwable;

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

        // Acréscimos e craque do jogo valem para o jogo no ar: trocar de jogo zera os dois.
        if ($gameChanged) {
            $channel->screen_settings = OverlayScreenSettings::normalize([...OverlayScreenSettings::normalize($channel->screen_settings), 'stoppage' => 0, 'mvp' => null, 'serve' => null]);
        } elseif (array_key_exists('stoppage_minutes', $data)) {
            $channel->screen_settings = OverlayScreenSettings::normalize([...OverlayScreenSettings::normalize($channel->screen_settings), 'stoppage' => (int) $data['stoppage_minutes']]);
        }

        if ($gameChanged) {
            $channel->stats_settings = OverlayStatsSettings::forNewGame($channel->stats_settings);
        }

        if (isset($data['stats_settings']) && is_array($data['stats_settings'])) {
            $channel->stats_settings = OverlayStatsSettings::apply($channel->stats_settings, $data['stats_settings']);
        }

        if (isset($data['screens_live']) && is_array($data['screens_live']) && array_key_exists('stats', $data['screens_live'])) {
            // Ligar a tela marca quando ela sai sozinha (duração > 0); ocultar limpa o prazo.
            $channel->stats_settings = OverlayStatsSettings::withExpiry($channel->stats_settings, (bool) $data['screens_live']['stats'], (int) (microtime(true) * 1000));
        }

        // Relatório no ar: mudar o que mostra recomeça do 1º slide; ligar a tela ou trocar o jogo (relatório da partida) também.
        $now = (int) (microtime(true) * 1000);
        $reportWasOn = $channel->screensLive()['report'];

        if (isset($data['report_settings']) && is_array($data['report_settings'])) {
            $channel->report_settings = OverlayReportSettings::apply($channel->report_settings, $data['report_settings'], $now);
        }

        $reportTurnsOn = ! $reportWasOn && isset($data['screens_live']) && is_array($data['screens_live']) && ($data['screens_live']['report'] ?? false) == true;

        if ($reportTurnsOn || ($gameChanged && OverlayReportSettings::normalize($channel->report_settings)['division_id'] === null)) {
            $channel->report_settings = OverlayReportSettings::restart(OverlayReportSettings::normalize($channel->report_settings), $now);
        }

        if (isset($data['report_command']) && is_string($data['report_command'])) {
            $channel->report_settings = OverlayReportSettings::command($channel->report_settings, $data['report_command'], $now);
        }

        if (isset($data['screens_live']) && is_array($data['screens_live'])) {
            $channel->layers = array_merge($channel->layers ?? [], [
                'screens' => array_merge($channel->screensLive(), array_intersect_key(array_map('boolval', $data['screens_live']), array_flip(OverlayChannel::SCREENS))),
            ]);
        }

        if (isset($data['screen_settings']) && is_array($data['screen_settings'])) {
            $channel->screen_settings = OverlayScreenSettings::apply($channel->screen_settings, $data['screen_settings'], (int) (microtime(true) * 1000));
        }

        if (isset($data['automation_settings']) && is_array($data['automation_settings'])) {
            $channel->automation_settings = OverlayAutomationSettings::normalize(array_merge($channel->automation_settings ?? [], $data['automation_settings']));
        }

        // Overlays livres (Builder): liga/desliga por ID; só vale para overlays que existem na organização.
        if (isset($data['custom_live']) && is_array($data['custom_live'])) {
            $known = OverlayCustomItem::query()->whereIn('id', array_keys($data['custom_live']))->pluck('id')->all();
            $custom = array_merge($channel->customLive(), array_intersect_key(array_map('boolval', $data['custom_live']), array_flip($known)));
            $channel->layers = array_merge($channel->layers ?? [], ['custom' => array_filter($custom)]);
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
        $watched = ['presentation', 'team', 'individual_mode', 'individual_scope', 'list_seconds', 'individual_seconds', 'formation_seconds', 'full_individuals'];
        $restart = $gameChanged || (! $lineupWasOn && $channel->layerOn('lineup')) || array_diff_assoc(array_intersect_key($lineup, array_flip($watched)), array_intersect_key($lineupBefore, array_flip($watched))) !== [];

        if ($restart) {
            $channel->lineup_settings = array_merge($lineup, ['step' => 0, 'anchor_ms' => (int) (microtime(true) * 1000)]);
        }

        // Comando do operador (próximo, anterior, reiniciar): vale na hora e é calculado aqui para duas pessoas não se atropelarem.
        if (isset($data['lineup_command']) && is_string($data['lineup_command'])) {
            $plan = OverlayState::lineupPlan($channel);
            $channel->lineup_settings = array_merge(
                OverlayLineupSettings::normalize($channel->lineup_settings),
                OverlayLineupTimeline::apply($data['lineup_command'], OverlayLineupSettings::normalize($channel->lineup_settings), $plan['individuals'], (int) (microtime(true) * 1000), $plan['formation']),
            );
        }

        if (isset($data['lower_third_settings']) && is_array($data['lower_third_settings'])) {
            $channel->lower_third_settings = OverlayLowerThirdSettings::normalize(array_merge($channel->lower_third_settings ?? [], $data['lower_third_settings']));
        }

        if (isset($data['sponsor_settings']) && is_array($data['sponsor_settings'])) {
            $channel->sponsor_settings = OverlaySponsorSettings::normalize(array_merge($channel->sponsor_settings ?? [], $data['sponsor_settings']));
        }

        $channel->save();
        OverlayUpdated::dispatchFor($channel);

        if (isset($data['lower_third_show']) && is_array($data['lower_third_show'])) {
            $this->showLowerThird($channel, $data['lower_third_show']);
        }

        return $channel;
    }

    /**
     * Faixa manual (aviso livre, jogador em destaque ou amostra de um lance): só sai se a tela de lances está no ar e nunca
     * é guardada. Falha do Reverb não desfaz nada (o operador clica de novo).
     *
     * @param  array<string, mixed>  $command
     */
    private function showLowerThird(OverlayChannel $channel, array $command): void
    {
        if (! $channel->layerOn('lower_third')) {
            return;
        }

        $game = $channel->game_id !== null ? Game::query()->find($channel->game_id) : null;
        $kind = (string) ($command['kind'] ?? '');

        $banner = match (true) {
            $kind === 'custom' => LowerThirdBanner::custom(trim((string) ($command['title'] ?? '')), isset($command['subtitle']) ? trim((string) $command['subtitle']) : null),
            $game === null => null,
            $kind === 'player' => LowerThirdBanner::forPlayer($game, (string) ($command['person_id'] ?? '')),
            default => LowerThirdBanner::sample($game, $kind),
        };

        if ($banner === null) {
            return;
        }

        try {
            event(new OverlayLowerThird($channel->token, $banner));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
