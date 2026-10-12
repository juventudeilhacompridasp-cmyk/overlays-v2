<?php

use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Standings\GameResult;
use App\Modules\Rules\Standings\StandingRow;
use App\Modules\Rules\Standings\StandingsCalculator;
use App\Modules\Rules\Tiebreaker;

function standings(array $teams, array $games, ?RuleSet $rules = null, array $cards = []): array
{
    return (new StandingsCalculator)->calculate($teams, $games, $rules ?? RuleSet::defaults(), $cards);
}

/** @return list<string> */
function order(array $rows): array
{
    return array_map(fn (StandingRow $row) => $row->teamId, $rows);
}

function row(array $rows, string $teamId): StandingRow
{
    return collect($rows)->firstWhere('teamId', $teamId);
}

it('scores 3 points for a win, 1 for a draw and 0 for a loss', function () {
    $rows = standings(['A', 'B', 'C'], [
        new GameResult('A', 'B', 2, 0),
        new GameResult('B', 'C', 1, 1),
        new GameResult('C', 'A', 0, 0),
    ]);

    expect(order($rows))->toBe(['A', 'C', 'B'])
        ->and(row($rows, 'A')->points)->toBe(4)
        ->and(row($rows, 'A')->wins)->toBe(1)
        ->and(row($rows, 'A')->draws)->toBe(1)
        ->and(row($rows, 'A')->goalDifference())->toBe(2)
        ->and(row($rows, 'B')->points)->toBe(1)
        ->and(row($rows, 'B')->losses)->toBe(1)
        ->and(row($rows, 'C')->points)->toBe(2)
        ->and(array_map(fn ($r) => $r->position, $rows))->toBe([1, 2, 3]);
});

it('breaks a points tie by number of wins before goal difference or goals scored', function () {
    // A: 1 vitória magra e uma goleada sofrida (3 pts, saldo -2, 1 gol pró).
    // B: três empates em 2 x 2 (3 pts, 0 vitórias, saldo 0, 6 gols pró). Vitórias vêm primeiro: A fica à frente.
    $rows = standings(['B', 'A', 'C', 'D', 'E'], [
        new GameResult('A', 'C', 1, 0),
        new GameResult('D', 'A', 3, 0),
        new GameResult('B', 'C', 2, 2),
        new GameResult('B', 'D', 2, 2),
        new GameResult('B', 'E', 2, 2),
    ]);

    expect(row($rows, 'A')->points)->toBe(3)
        ->and(row($rows, 'B')->points)->toBe(3)
        ->and(row($rows, 'A')->goalDifference())->toBe(-2)
        ->and(row($rows, 'B')->goalsFor)->toBe(6)
        ->and(array_search('A', order($rows)))->toBeLessThan(array_search('B', order($rows)));
});

it('breaks a tie by goal difference before goals scored', function () {
    // Ambos com 1 vitória e 1 derrota. A: saldo +1 e 2 gols pró. B: saldo -2 e 6 gols pró.
    $rows = standings(['B', 'A', 'C', 'D', 'E', 'F'], [
        new GameResult('A', 'C', 2, 0),
        new GameResult('D', 'A', 1, 0),
        new GameResult('B', 'E', 6, 3),
        new GameResult('F', 'B', 5, 0),
    ]);

    expect(row($rows, 'A')->wins)->toBe(row($rows, 'B')->wins)
        ->and(row($rows, 'A')->goalDifference())->toBe(1)
        ->and(row($rows, 'B')->goalDifference())->toBe(-2)
        ->and(row($rows, 'B')->goalsFor)->toBeGreaterThan(row($rows, 'A')->goalsFor)
        ->and(array_search('A', order($rows)))->toBeLessThan(array_search('B', order($rows)));
});

it('breaks a tie by goals scored after goal difference', function () {
    $rows = standings(['B', 'A', 'C', 'D'], [
        new GameResult('A', 'C', 2, 1),
        new GameResult('D', 'A', 2, 1),
        new GameResult('B', 'C', 1, 0),
        new GameResult('D', 'B', 1, 0),
    ]);

    expect(row($rows, 'A')->goalDifference())->toBe(row($rows, 'B')->goalDifference())
        ->and(row($rows, 'A')->goalsFor)->toBe(3)
        ->and(row($rows, 'B')->goalsFor)->toBe(1)
        ->and(array_search('A', order($rows)))->toBeLessThan(array_search('B', order($rows)));
});

it('uses head-to-head when exactly two teams are still tied', function () {
    // A e B empatam em pontos, vitórias, saldo e gols pró; A venceu o confronto direto.
    $rows = standings(['B', 'A', 'C', 'D'], [
        new GameResult('A', 'B', 1, 0),
        new GameResult('C', 'A', 1, 0),
        new GameResult('B', 'D', 1, 0),
    ]);

    expect(row($rows, 'A')->points)->toBe(3)
        ->and(row($rows, 'B')->points)->toBe(3)
        ->and(row($rows, 'A')->goalsFor)->toBe(row($rows, 'B')->goalsFor)
        ->and(array_search('A', order($rows)))->toBeLessThan(array_search('B', order($rows)));

    $teamA = row($rows, 'A');
    expect($teamA->tied)->toBeFalse();
});

it('applies head-to-head before fewer red cards', function () {
    // A e B empatados em tudo; A venceu o confronto direto mas tem mais vermelhos: o confronto direto vem antes.
    $rows = standings(['B', 'A', 'C', 'D'], [
        new GameResult('A', 'B', 1, 0),
        new GameResult('C', 'A', 1, 0),
        new GameResult('B', 'D', 1, 0),
    ], cards: ['A' => ['red' => 3], 'B' => ['red' => 0]]);

    expect(array_search('A', order($rows)))->toBeLessThan(array_search('B', order($rows)));
});

it('skips head-to-head when three teams are tied and falls through to fewer red cards', function () {
    $games = [
        new GameResult('A', 'B', 1, 0),
        new GameResult('B', 'C', 1, 0),
        new GameResult('C', 'A', 1, 0),
    ];

    $rows = standings(['A', 'B', 'C'], $games, cards: [
        'A' => ['red' => 2],
        'B' => ['red' => 0],
        'C' => ['red' => 1],
    ]);

    expect(order($rows))->toBe(['B', 'C', 'A'])
        ->and(array_map(fn ($r) => $r->tied, $rows))->toBe([false, false, false]);
});

it('falls through to fewer yellow cards when red cards are equal', function () {
    $games = [
        new GameResult('A', 'B', 1, 0),
        new GameResult('B', 'C', 1, 0),
        new GameResult('C', 'A', 1, 0),
    ];

    $rows = standings(['A', 'B', 'C'], $games, cards: [
        'A' => ['yellow' => 3],
        'B' => ['yellow' => 1],
        'C' => ['yellow' => 2],
    ]);

    expect(order($rows))->toBe(['B', 'C', 'A']);
});

it('flags a tie that only a draw can solve and keeps the given order', function () {
    $rows = standings(['A', 'B', 'C'], [
        new GameResult('A', 'B', 1, 0),
        new GameResult('B', 'C', 1, 0),
        new GameResult('C', 'A', 1, 0),
    ]);

    expect(order($rows))->toBe(['A', 'B', 'C'])
        ->and(array_map(fn ($r) => $r->tied, $rows))->toBe([true, true, true]);
});

it('applies later criteria only to the teams that are still tied', function () {
    // A, B e C empatados em pontos; o saldo separa A (melhor) de B e C, que seguem empatados e vão ao confronto direto.
    $rows = standings(['C', 'B', 'A', 'D'], [
        new GameResult('A', 'D', 3, 0),
        new GameResult('D', 'A', 1, 0),
        new GameResult('B', 'C', 1, 0),
        new GameResult('D', 'B', 1, 0),
        new GameResult('C', 'D', 1, 0),
    ]);

    expect(row($rows, 'A')->points)->toBe(3)
        ->and(row($rows, 'B')->points)->toBe(3)
        ->and(row($rows, 'C')->points)->toBe(3)
        ->and(row($rows, 'D')->points)->toBe(6)
        ->and(order($rows))->toBe(['D', 'A', 'B', 'C']);
});

it('awards the configured score on a walkover', function () {
    $rules = RuleSet::defaults();

    $rows = standings(['A', 'B'], [GameResult::walkover('A', 'B', $rules)], $rules);

    expect(row($rows, 'A')->points)->toBe(3)
        ->and(row($rows, 'A')->goalsFor)->toBe(3)
        ->and(row($rows, 'B')->points)->toBe(0)
        ->and(row($rows, 'B')->goalsAgainst)->toBe(3)
        ->and(order($rows))->toBe(['A', 'B']);
});

it('handles a walkover won by the away team', function () {
    $rules = RuleSet::fromArray(['walkover' => ['winner_goals' => 1, 'loser_goals' => 0]]);

    $game = GameResult::walkover('B', 'A', $rules, winnerIsHome: false);

    expect($game->homeId)->toBe('A')
        ->and($game->awayId)->toBe('B')
        ->and($game->homeGoals)->toBe(0)
        ->and($game->awayGoals)->toBe(1)
        ->and($game->walkover)->toBeTrue();
});

it('uses custom points and tiebreaker order from the rule set', function () {
    // Só saldo de gols e depois sorteio; vitória vale 2.
    $rules = RuleSet::fromArray([
        'points' => ['win' => 2, 'draw' => 1, 'loss' => 0],
        'tiebreakers' => ['goal_difference', 'draw'],
    ]);

    $rows = standings(['A', 'B', 'C', 'D'], [
        new GameResult('A', 'C', 1, 0),
        new GameResult('B', 'D', 5, 0),
    ], $rules);

    expect(row($rows, 'A')->points)->toBe(2)
        ->and(order($rows)[0])->toBe('B');
});

it('lists teams that have not played yet at the bottom with zero stats', function () {
    $rows = standings(['A', 'B', 'C'], [new GameResult('A', 'B', 1, 0)]);

    expect(row($rows, 'C')->played)->toBe(0)
        ->and(row($rows, 'C')->points)->toBe(0)
        ->and(row($rows, 'C')->efficiency())->toBe(0.0);
});

it('computes efficiency as a percentage of the points played for', function () {
    $rows = standings(['A', 'B'], [new GameResult('A', 'B', 1, 1), new GameResult('B', 'A', 0, 2)]);

    expect(row($rows, 'A')->efficiency())->toBe(66.7);
});

it('rejects games from teams outside the standings', function () {
    standings(['A', 'B'], [new GameResult('A', 'Z', 1, 0)]);
})->throws(InvalidArgumentException::class);

it('supports goals against as an explicit tiebreaker', function () {
    $rules = new RuleSet(tiebreakers: [Tiebreaker::GoalsAgainst, Tiebreaker::Draw]);

    $rows = standings(['A', 'B', 'C', 'D'], [
        new GameResult('A', 'C', 1, 0),
        new GameResult('B', 'D', 4, 3),
    ], $rules);

    expect(order($rows)[0])->toBe('A');
});
