<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Apaga a tabela de jogos de uma fase (e os grupos gerados com ela) para poder gerar de novo.
 * Só é permitido enquanto nenhum jogo foi realizado: sem jogos encerrados, em andamento ou com eventos.
 */
final class ClearStageFixtures
{
    public function handle(Stage $stage): int
    {
        $games = Game::query()->where('stage_id', $stage->getKey())->get();

        $started = $games->contains(fn (Game $game) => in_array($game->status, [GameStatus::Finished, GameStatus::Walkover, GameStatus::InProgress], true));

        if ($started || GameEvent::query()->whereIn('game_id', $games->pluck('id'))->exists()) {
            throw new InvalidArgumentException('Há jogos realizados nesta fase. Reabra e apague os lançamentos antes de limpar a tabela.');
        }

        return DB::transaction(function () use ($stage, $games): int {
            Game::query()->whereIn('id', $games->pluck('id'))->delete();
            $stage->groups()->delete();

            return $games->count();
        });
    }
}
