<?php

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Models\Entry;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Rules\Discipline\DisciplineTracker;
use App\Modules\Rules\Discipline\TeamGameRecord;

/**
 * Suspensão automática por cartões de uma inscrição, processando seus jogos encerrados em ordem
 * (data, rodada, número do jogo). Retorna o tracker já processado: `suspended()`, `isSuspended()`, etc.
 * Penas do tribunal disciplinar ficam fora (lançamento manual).
 */
final class CalculateDiscipline
{
    public function forEntry(Entry $entry): DisciplineTracker
    {
        $tracker = new DisciplineTracker($entry->division->ruleSet());

        $games = Game::query()
            ->where(fn ($query) => $query->where('home_entry_id', $entry->getKey())->orWhere('away_entry_id', $entry->getKey()))
            ->whereIn('status', [GameStatus::Finished, GameStatus::Walkover])
            ->orderBy('scheduled_at')
            ->orderBy('round')
            ->orderBy('number')
            ->orderBy('id')
            ->get();

        $cardsByGame = [];

        GameEvent::query()
            ->effective()
            ->whereIn('game_id', $games->pluck('id'))
            ->where('entry_id', $entry->getKey())
            ->whereNotNull('person_id')
            ->whereIn('type', [GameEventType::YellowCard, GameEventType::RedCard])
            ->get(['game_id', 'person_id', 'type'])
            ->each(function (GameEvent $event) use (&$cardsByGame): void {
                $key = $event->type === GameEventType::YellowCard ? 'yellow' : 'red';

                $cardsByGame[$event->game_id][$event->person_id] ??= ['yellow' => 0, 'red' => 0];
                $cardsByGame[$event->game_id][$event->person_id][$key]++;
            });

        foreach ($games as $game) {
            $tracker->apply(new TeamGameRecord(
                gameId: (string) $game->getKey(),
                stageId: $game->stage_id,
                countsAsServed: true,
                cards: $game->status === GameStatus::Walkover ? [] : ($cardsByGame[$game->getKey()] ?? []),
            ));
        }

        return $tracker;
    }
}
