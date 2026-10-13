<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Rules\Scoring\SetMatch;
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

        $model = $game->sport()->profile()->scoreModel;

        if ($model === 'sets') {
            return $this->projectSets($game);
        }

        if ($model === 'points') {
            return $this->projectPoints($game);
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

    /**
     * Esportes de sets (vôlei): `home_score`/`away_score` são os SETS ganhos e `sets` guarda os pontos de cada set,
     * ambos derivados da sequência de pontos de rally (ver ADR 0002).
     */
    private function projectSets(Game $game): Game
    {
        $state = (new SetMatch($game->division->ruleSet()))->state(self::pointSides($game));

        $game->forceFill([
            'home_score' => $state['won']['home'],
            'away_score' => $state['won']['away'],
            'home_penalties' => null,
            'away_penalties' => null,
            'sets' => $state['sets'],
        ])->save();

        return $game;
    }

    /**
     * Esportes de pontos (basquete): o placar é a soma dos lances (1/2/3). Um resultado rápido guarda o total
     * no `payload.points` de um único lançamento anônimo.
     */
    private function projectPoints(Game $game): Game
    {
        $totals = [(string) $game->home_entry_id => 0, (string) $game->away_entry_id => 0];

        foreach ($game->events()->effective()->whereIn('type', GameEventType::scoring())->get() as $event) {
            if (isset($totals[(string) $event->entry_id])) {
                $totals[(string) $event->entry_id] += (int) ($event->payload['points'] ?? $event->type->pointValue() ?? 0);
            }
        }

        $game->forceFill([
            'home_score' => $totals[(string) $game->home_entry_id],
            'away_score' => $totals[(string) $game->away_entry_id],
            'home_penalties' => null,
            'away_penalties' => null,
        ])->save();

        return $game;
    }

    /**
     * Lado de cada ponto de rally válido, em ordem.
     *
     * @return list<'home'|'away'>
     */
    public static function pointSides(Game $game): array
    {
        $sides = [];

        foreach ($game->events()->effective()->where('type', GameEventType::Point)->orderBy('id')->get(['entry_id']) as $event) {
            $sides[] = $event->entry_id === $game->home_entry_id ? 'home' : 'away';
        }

        return $sides;
    }
}
