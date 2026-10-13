<?php

namespace App\Modules\Rules\Standings;

use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Tiebreaker;
use InvalidArgumentException;

/**
 * Calcula a classificação de uma fase/grupo (pontos corridos). Código puro, sem banco.
 *
 * Desempate: os pontos vêm primeiro; depois cada critério do regulamento é aplicado, na ordem,
 * apenas às equipes que continuam empatadas. Equipes que nenhum critério calculável separa saem
 * marcadas com `tied = true` (decisão por sorteio) e mantêm a ordem em que foram informadas.
 */
final class StandingsCalculator
{
    /**
     * @param  list<string>  $teamIds  Participantes, na ordem de sorteio/cabeça de chave (usada como ordem final estável).
     * @param  iterable<GameResult>  $games  Somente jogos encerrados (inclui W.O.).
     * @param  array<string, array{yellow?: int, red?: int, blue?: int}>  $cards  Cartões acumulados por equipe.
     * @return list<StandingRow>
     */
    public function calculate(array $teamIds, iterable $games, RuleSet $rules, array $cards = []): array
    {
        $stats = [];
        foreach ($teamIds as $id) {
            $stats[$id] = ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'gf' => 0, 'ga' => 0, 'pf' => 0, 'pa' => 0, 'points' => 0];
        }

        $results = [];
        foreach ($games as $game) {
            foreach ([$game->homeId, $game->awayId] as $id) {
                if (! isset($stats[$id])) {
                    throw new InvalidArgumentException("Equipe {$id} não participa desta classificação.");
                }
            }

            $results[] = $game;
            $this->register($stats, $game->homeId, $game->homeGoals, $game->awayGoals, $rules, $game->homePoints, $game->awayPoints);
            $this->register($stats, $game->awayId, $game->awayGoals, $game->homeGoals, $rules, $game->awayPoints, $game->homePoints);
        }

        $ordered = $this->order($teamIds, $stats, $results, $cards, $rules);

        $rows = [];
        foreach ($ordered as $index => [$id, $tied]) {
            $s = $stats[$id];
            $rows[] = new StandingRow(
                teamId: $id,
                position: $index + 1,
                played: $s['played'],
                wins: $s['wins'],
                draws: $s['draws'],
                losses: $s['losses'],
                goalsFor: $s['gf'],
                goalsAgainst: $s['ga'],
                points: $s['points'],
                yellowCards: $cards[$id]['yellow'] ?? 0,
                redCards: $cards[$id]['red'] ?? 0,
                blueCards: $cards[$id]['blue'] ?? 0,
                tied: $tied,
                scoreFor: $s['pf'],
                scoreAgainst: $s['pa'],
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, int>>  $stats
     */
    private function register(array &$stats, string $teamId, int $goalsFor, int $goalsAgainst, RuleSet $rules, int $pointsFor = 0, int $pointsAgainst = 0): void
    {
        $stats[$teamId]['played']++;
        $stats[$teamId]['gf'] += $goalsFor;
        $stats[$teamId]['ga'] += $goalsAgainst;
        $stats[$teamId]['pf'] += $pointsFor;
        $stats[$teamId]['pa'] += $pointsAgainst;

        // Esportes de sets: vitória/derrota por placar apertado (ex.: 3-2) pode valer pontos diferentes.
        $narrow = $rules->scoreModel === 'sets' && abs($goalsFor - $goalsAgainst) === 1;

        if ($goalsFor > $goalsAgainst) {
            $stats[$teamId]['wins']++;
            $stats[$teamId]['points'] += $narrow ? ($rules->pointsNarrowWin ?? $rules->pointsWin) : $rules->pointsWin;
        } elseif ($goalsFor === $goalsAgainst) {
            $stats[$teamId]['draws']++;
            $stats[$teamId]['points'] += $rules->pointsDraw;
        } else {
            $stats[$teamId]['losses']++;
            $stats[$teamId]['points'] += $narrow ? ($rules->pointsNarrowLoss ?? $rules->pointsLoss) : $rules->pointsLoss;
        }
    }

    /** Razão em milésimos de ponto base (inteiro, para agrupar): sem perdas, vale muito mais que qualquer razão com perdas. */
    private function ratio(int $made, int $suffered): int
    {
        return $suffered === 0 ? ($made > 0 ? 1_000_000_000 + $made : 0) : intdiv($made * 100_000, $suffered);
    }

    /**
     * @param  list<string>  $teamIds
     * @param  array<string, array<string, int>>  $stats
     * @param  list<GameResult>  $results
     * @param  array<string, array{yellow?: int, red?: int, blue?: int}>  $cards
     * @return list<array{0: string, 1: bool}>
     */
    private function order(array $teamIds, array $stats, array $results, array $cards, RuleSet $rules): array
    {
        // Agrupa por pontos (maior primeiro), preservando a ordem informada dentro de cada grupo.
        $byPoints = [];
        foreach ($teamIds as $id) {
            $byPoints[$stats[$id]['points']][] = $id;
        }
        krsort($byPoints);

        $ordered = [];
        foreach ($byPoints as $cluster) {
            array_push($ordered, ...$this->resolve($cluster, 0, $stats, $results, $cards, $rules));
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $cluster  Equipes empatadas até aqui.
     * @param  array<string, array<string, int>>  $stats
     * @param  list<GameResult>  $results
     * @param  array<string, array{yellow?: int, red?: int, blue?: int}>  $cards
     * @return list<array{0: string, 1: bool}>
     */
    private function resolve(array $cluster, int $from, array $stats, array $results, array $cards, RuleSet $rules): array
    {
        if (count($cluster) === 1) {
            return [[$cluster[0], false]];
        }

        $criteria = $rules->tiebreakers;

        for ($i = $from; $i < count($criteria); $i++) {
            if ($criteria[$i] === Tiebreaker::Draw) {
                break;
            }

            $groups = $this->partition($cluster, $criteria[$i], $stats, $results, $cards, $rules);

            if ($groups === null || count($groups) === 1) {
                continue; // critério não se aplica ou não separa ninguém
            }

            $out = [];
            foreach ($groups as $group) {
                array_push($out, ...$this->resolve($group, $i + 1, $stats, $results, $cards, $rules));
            }

            return $out;
        }

        return array_map(fn (string $id) => [$id, true], $cluster);
    }

    /**
     * Divide o grupo empatado em subgrupos ordenados do melhor para o pior pelo critério.
     * Retorna null quando o critério não se aplica ao grupo.
     *
     * @param  list<string>  $cluster
     * @param  array<string, array<string, int>>  $stats
     * @param  list<GameResult>  $results
     * @param  array<string, array{yellow?: int, red?: int, blue?: int}>  $cards
     * @return list<list<string>>|null
     */
    private function partition(array $cluster, Tiebreaker $criterion, array $stats, array $results, array $cards, RuleSet $rules): ?array
    {
        if ($criterion === Tiebreaker::HeadToHead) {
            return $this->headToHead($cluster, $results, $rules);
        }

        // [função que extrai o valor, true se "maior é melhor"]
        [$value, $descending] = match ($criterion) {
            Tiebreaker::Wins => [fn (string $id) => $stats[$id]['wins'], true],
            Tiebreaker::GoalDifference => [fn (string $id) => $stats[$id]['gf'] - $stats[$id]['ga'], true],
            Tiebreaker::GoalsFor => [fn (string $id) => $stats[$id]['gf'], true],
            Tiebreaker::GoalsAgainst => [fn (string $id) => $stats[$id]['ga'], false],
            Tiebreaker::SetRatio => [fn (string $id) => $this->ratio($stats[$id]['gf'], $stats[$id]['ga']), true],
            Tiebreaker::PointRatio => [fn (string $id) => $this->ratio($stats[$id]['pf'], $stats[$id]['pa']), true],
            Tiebreaker::FewestRedCards => [fn (string $id) => $cards[$id]['red'] ?? 0, false],
            Tiebreaker::FewestYellowCards => [fn (string $id) => $cards[$id]['yellow'] ?? 0, false],
            Tiebreaker::FewestBlueCards => [fn (string $id) => $cards[$id]['blue'] ?? 0, false],
            default => [null, true],
        };

        if ($value === null) {
            return null;
        }

        $buckets = [];
        foreach ($cluster as $id) {
            $buckets[$value($id)][] = $id;
        }

        $descending ? krsort($buckets) : ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Confronto direto: só quando restam exatamente duas equipes. Vence quem somou mais pontos nos jogos
     * entre elas; persistindo, o saldo de gols nesses jogos.
     *
     * @param  list<string>  $cluster
     * @param  list<GameResult>  $results
     * @return list<list<string>>|null
     */
    private function headToHead(array $cluster, array $results, RuleSet $rules): ?array
    {
        if (count($cluster) !== 2) {
            return null;
        }

        [$a, $b] = $cluster;
        $points = [$a => 0, $b => 0];
        $diff = [$a => 0, $b => 0];

        foreach ($results as $game) {
            $pair = [$game->homeId, $game->awayId];
            if (! in_array($a, $pair, true) || ! in_array($b, $pair, true)) {
                continue;
            }

            $diff[$game->homeId] += $game->homeGoals - $game->awayGoals;
            $diff[$game->awayId] += $game->awayGoals - $game->homeGoals;

            if ($game->homeGoals > $game->awayGoals) {
                $points[$game->homeId] += $rules->pointsWin;
                $points[$game->awayId] += $rules->pointsLoss;
            } elseif ($game->homeGoals < $game->awayGoals) {
                $points[$game->awayId] += $rules->pointsWin;
                $points[$game->homeId] += $rules->pointsLoss;
            } else {
                $points[$a] += $rules->pointsDraw;
                $points[$b] += $rules->pointsDraw;
            }
        }

        foreach ([$points, $diff] as $metric) {
            if ($metric[$a] !== $metric[$b]) {
                return $metric[$a] > $metric[$b] ? [[$a], [$b]] : [[$b], [$a]];
            }
        }

        return [[$a, $b]];
    }
}
