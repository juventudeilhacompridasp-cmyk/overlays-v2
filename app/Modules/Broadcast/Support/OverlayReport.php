<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Competitions\Models\Division;
use App\Modules\Matches\Models\Game;
use App\Modules\Reports\Enums\ReportKind;
use App\Modules\Reports\Support\ChampionshipReport;
use App\Modules\Reports\Support\GameReport;

/**
 * Estado da tela "Relatório" (só com a tela no ar): a versão PÚBLICA do relatório (nomes por PublicName, cartões só com
 * `game_cards`, nada de IDs) e o ritmo dos slides. Partida = o jogo no ar; campeonato = a divisão escolhida ou a do jogo.
 */
final class OverlayReport
{
    /** @return array<string, mixed>|null */
    public static function for(OverlayChannel $channel, ?Game $game): ?array
    {
        $settings = OverlayReportSettings::normalize($channel->report_settings);

        $document = match (ReportKind::from($settings['kind'])) {
            ReportKind::Game => $game === null ? null : GameReport::build($game, $settings['sections'], true),
            ReportKind::Championship => ($division = self::division($settings['division_id'], $game)) === null ? null : ChampionshipReport::build($division, $settings['sections'], true),
        };

        if ($document === null) {
            return null;
        }

        return [
            'mode' => $settings['mode'], 'seconds' => $settings['seconds'], 'step' => $settings['step'], 'anchor_ms' => $settings['anchor_ms'],
            'style' => $settings['style'], 'animation' => $settings['animation'], 'scale' => $settings['scale'],
            'count' => count($document['sections']),
            'document' => $document,
        ];
    }

    private static function division(?string $id, ?Game $game): ?Division
    {
        if ($id !== null) {
            return Division::query()->find($id);
        }

        return $game?->division_id !== null ? Division::query()->find($game->division_id) : null;
    }
}
