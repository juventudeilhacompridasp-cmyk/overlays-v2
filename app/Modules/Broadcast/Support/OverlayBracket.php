<?php

namespace App\Modules\Broadcast\Support;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Support\BracketView;

/**
 * Chaveamento do mata-mata do jogo no ar (ou da última fase eliminatória da divisão), em formato plano para o OBS: confrontos
 * por rodada com placar agregado, vencedor, pênaltis e campeão. Parte do {@see BracketView}; sem links nem atletas.
 */
final class OverlayBracket
{
    /**
     * @return array{title: string, rounds: list<array{label: string, ties: list<array<string, mixed>>}>, champion: string|null}|null
     */
    public static function for(Game $game): ?array
    {
        $game->loadMissing('stage');
        $stage = $game->stage->type === StageType::Knockout
            ? $game->stage
            : Stage::query()->where('division_id', $game->division_id)->where('type', StageType::Knockout)->orderByDesc('sequence')->first();

        $view = $stage === null ? null : BracketView::for($stage);

        if ($stage === null || $view === null) {
            return null;
        }

        $side = fn (array $side): array => [
            'name' => $side['team']['name'] ?? 'A definir',
            'short' => $side['team']['short_name'] ?? null,
            'logo' => $side['team']['logo_url'] ?? null,
            'score' => $side['score'],
            'winner' => $side['winner'],
        ];

        return [
            'title' => $stage->name,
            'rounds' => array_map(fn (array $round): array => [
                'label' => $round['label'],
                'ties' => array_map(fn (array $tie): array => [
                    'a' => $side($tie['a']),
                    'b' => $side($tie['b']),
                    'decided' => $tie['decided'],
                    'live' => $tie['live'],
                    'penalties' => $tie['penalties'],
                ], $round['ties']),
            ], $view['rounds']),
            'champion' => $view['champion']['name'] ?? null,
        ];
    }
}
