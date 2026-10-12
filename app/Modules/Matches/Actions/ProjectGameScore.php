<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use InvalidArgumentException;

/**
 * Recalcula o placar do jogo a partir dos eventos válidos (a fonte da verdade são os eventos).
 * Gol contra conta para o adversário da equipe do jogador. Cobranças de pênaltis (disputa) vão para
 * home_penalties/away_penalties apenas se houver cobranças registradas.
 */
final class ProjectGameScore
{
    public function handle(Game $game): Game
    {
        $home = $game->home_entry_id;
        $away = $game->away_entry_id;

        if ($home === null || $away === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        $events = $game->events()
            ->effective()
            ->whereIn('type', [GameEventType::Goal, GameEventType::OwnGoal, GameEventType::PenaltyKick])
            ->get();

        $score = [$home => 0, $away => 0];
        $penalties = [$home => 0, $away => 0];
        $hasPenalties = false;

        foreach ($events as $event) {
            $team = $event->entry_id;

            if ($team !== $home && $team !== $away) {
                continue;
            }

            $opponent = $team === $home ? $away : $home;

            if ($event->type === GameEventType::Goal) {
                $score[$team]++;
            } elseif ($event->type === GameEventType::OwnGoal) {
                $score[$opponent]++;
            } else {
                $hasPenalties = true;

                if (($event->payload['scored'] ?? false) === true) {
                    $penalties[$team]++;
                }
            }
        }

        $game->forceFill([
            'home_score' => $score[$home],
            'away_score' => $score[$away],
            'home_penalties' => $hasPenalties ? $penalties[$home] : null,
            'away_penalties' => $hasPenalties ? $penalties[$away] : null,
        ])->save();

        return $game;
    }
}
