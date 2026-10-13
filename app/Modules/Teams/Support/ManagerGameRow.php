<?php

namespace App\Modules\Teams\Support;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\LineupSlot;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameLineup;
use App\Modules\Matches\Models\GameTactic;
use Illuminate\Support\Collection;

/** Uma linha de jogo na área do gestor: só dados públicos do jogo, do ponto de vista da inscrição dele. */
final class ManagerGameRow
{
    /** @return array<string, mixed> */
    public static function of(Game $game, Entry $mine): array
    {
        $home = $game->home_entry_id === $mine->getKey();
        $opponent = $home ? $game->awayEntry : $game->homeEntry;

        return [
            'id' => $game->getKey(),
            'home' => $home,
            'my_team' => $mine->team->name,
            'my_logo' => $mine->team->logoUrl(128),
            'opponent' => $opponent?->team->name,
            'opponent_logo' => $opponent?->team->logoUrl(128),
            'my_score' => $home ? $game->home_score : $game->away_score,
            'opponent_score' => $home ? $game->away_score : $game->home_score,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'decided' => $game->status->isDecided(),
            'scheduled_at' => $game->scheduled_at?->format('d/m/Y H:i'),
            'venue' => $game->venue?->name,
            'competition' => "{$game->division->season->competition->name} · {$game->division->name}",
            'stage' => $game->stage->name,
            'round' => $game->round,
        ];
    }

    /**
     * Situação da escalação da equipe do gestor em cada jogo: `confirmed`, `draft` (já tem titulares) ou `missing`.
     *
     * @param  Collection<int, Game>  $games
     * @param  callable(Game): Entry  $mine  a inscrição do gestor no jogo
     * @return array<string, string> jogo => situação
     */
    public static function lineupStatuses(Collection $games, callable $mine): array
    {
        $pairs = $games->mapWithKeys(fn (Game $game) => [$game->getKey() => $mine($game)->getKey()]);

        if ($pairs->isEmpty()) {
            return [];
        }

        $key = fn ($row): string => $row->game_id.'|'.$row->entry_id;

        $confirmed = GameTactic::query()->whereIn('game_id', $pairs->keys())->whereNotNull('confirmed_at')->get(['game_id', 'entry_id'])->map($key)->flip();
        $drafts = GameLineup::query()->whereIn('game_id', $pairs->keys())->where('slot', LineupSlot::Starter)->get(['game_id', 'entry_id'])->map($key)->flip();

        return $pairs->map(function (string $entryId, string $gameId) use ($confirmed, $drafts): string {
            $pair = $gameId.'|'.$entryId;

            return $confirmed->has($pair) ? 'confirmed' : ($drafts->has($pair) ? 'draft' : 'missing');
        })->all();
    }
}
