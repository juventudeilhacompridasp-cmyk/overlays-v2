<?php

namespace App\Modules\Rules\Scoring;

use App\Modules\Rules\RuleSet;

/**
 * Placar de esportes de sets (vôlei), derivado da sequência de pontos de rally. Código puro, sem banco.
 *
 * Um set acaba quando um lado chega à pontuação do set (a do set decisivo é menor) com a vantagem mínima;
 * a partida acaba quando um lado ganha `setsToWin` sets. Pontos depois do fim da partida são inválidos.
 */
final class SetMatch
{
    public function __construct(private readonly RuleSet $rules) {}

    /** Número do set decisivo (5º numa melhor de 5). */
    public function decidingSet(): int
    {
        return 2 * $this->rules->setsToWin - 1;
    }

    public function targetFor(int $set): int
    {
        return $set === $this->decidingSet() ? $this->rules->decidingSetPoints : $this->rules->setPoints;
    }

    /**
     * @param  list<'home'|'away'>  $points  lado que fez cada ponto, em ordem
     * @return array{sets: list<array{home: int, away: int, winner: 'home'|'away'|null, finished: bool}>, won: array{home: int, away: int}, winner: 'home'|'away'|null, finished: bool, current: int|null}
     */
    public function state(array $points): array
    {
        $sets = [];
        $won = ['home' => 0, 'away' => 0];
        $set = 1;
        $score = ['home' => 0, 'away' => 0];
        $winner = null;

        foreach ($points as $side) {
            if ($winner !== null) {
                break; // ponto depois do fim da partida: ignorado (o lançamento é barrado em canScore)
            }

            $score[$side]++;
            $other = $side === 'home' ? 'away' : 'home';

            if ($score[$side] >= $this->targetFor($set) && $this->rules->setMinLead <= $score[$side] - $score[$other]) {
                $sets[] = ['home' => $score['home'], 'away' => $score['away'], 'winner' => $side, 'finished' => true];
                $won[$side]++;
                $score = ['home' => 0, 'away' => 0];

                if ($won[$side] >= $this->rules->setsToWin) {
                    $winner = $side;
                } else {
                    $set++;
                }
            }
        }

        if ($winner === null) {
            $sets[] = ['home' => $score['home'], 'away' => $score['away'], 'winner' => null, 'finished' => false];
        }

        return ['sets' => $sets, 'won' => $won, 'winner' => $winner, 'finished' => $winner !== null, 'current' => $winner === null ? $set : null];
    }

    /**
     * Mensagem de erro se não dá para lançar mais um ponto, ou null.
     *
     * @param  list<'home'|'away'>  $points
     */
    public function canScore(array $points): ?string
    {
        return $this->state($points)['finished'] ? 'A partida já terminou: não cabem mais pontos.' : null;
    }
}
