<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Scoring\SetMatch;
use App\Modules\Rules\Standings\GameResult;
use App\Modules\Rules\Standings\StandingsCalculator;

function volleyballRules(array $override = []): RuleSet
{
    return RuleSet::fromArray($override, Sport::Volleyball->profile()->ruleDefaults);
}

/** @return list<'home'|'away'> */
function rally(string $side, int $n): array
{
    return array_fill(0, $n, $side);
}

it('defines volleyball as a best-of-five of 25 points with a 15-point decider', function () {
    $rules = volleyballRules();

    expect($rules->scoreModel)->toBe('sets')
        ->and($rules->setsToWin)->toBe(3)
        ->and($rules->setPoints)->toBe(25)
        ->and($rules->decidingSetPoints)->toBe(15)
        ->and($rules->setMinLead)->toBe(2)
        ->and($rules->maxStarters)->toBe(6)
        ->and(array_map(fn ($t) => $t->value, $rules->tiebreakers))->toBe(['wins', 'set_ratio', 'point_ratio', 'head_to_head', 'draw'])
        ->and(Sport::Volleyball->profile()->hasClock())->toBeFalse();
});

it('keeps the score model out of the saved rules', function () {
    $rules = volleyballRules(['scoring' => ['model' => 'goals']]);

    expect($rules->scoreModel)->toBe('sets')->and(RuleSet::defaults()->scoreModel)->toBe('goals');
});

it('closes a set at 25 with a two-point lead', function () {
    $match = new SetMatch(volleyballRules());
    $state = $match->state(rally('home', 25));

    expect($state['sets'][0])->toBe(['home' => 25, 'away' => 0, 'winner' => 'home', 'finished' => true])
        ->and($state['won'])->toBe(['home' => 1, 'away' => 0])
        ->and($state['current'])->toBe(2)
        ->and($state['sets'][1])->toBe(['home' => 0, 'away' => 0, 'winner' => null, 'finished' => false]);
});

it('keeps playing past 25 until someone leads by two', function () {
    $match = new SetMatch(volleyballRules());
    $points = [...rally('home', 24), ...rally('away', 24)];

    expect($match->state([...$points, 'home'])['sets'][0]['finished'])->toBeFalse()
        ->and($match->state([...$points, 'home', 'away'])['sets'][0]['finished'])->toBeFalse();

    $closed = $match->state([...$points, 'home', 'home'])['sets'][0];
    expect($closed)->toBe(['home' => 26, 'away' => 24, 'winner' => 'home', 'finished' => true]);
});

it('ends the match at three sets and refuses more points', function () {
    $match = new SetMatch(volleyballRules());
    $points = [...rally('home', 25), ...rally('home', 25), ...rally('home', 25)];
    $state = $match->state($points);

    expect($state['finished'])->toBeTrue()
        ->and($state['winner'])->toBe('home')
        ->and($state['current'])->toBeNull()
        ->and($state['sets'])->toHaveCount(3)
        ->and($match->canScore($points))->toBe('A partida já terminou: não cabem mais pontos.')
        ->and($match->canScore(rally('home', 24)))->toBeNull();
});

it('plays the fifth set to 15', function () {
    $match = new SetMatch(volleyballRules());
    $twoTwo = [...rally('home', 25), ...rally('away', 25), ...rally('home', 25), ...rally('away', 25)];

    expect($match->decidingSet())->toBe(5)
        ->and($match->state([...$twoTwo, ...rally('away', 14)])['finished'])->toBeFalse();

    $done = $match->state([...$twoTwo, ...rally('away', 15)]);
    expect($done['finished'])->toBeTrue()
        ->and($done['winner'])->toBe('away')
        ->and($done['won'])->toBe(['home' => 2, 'away' => 3])
        ->and($done['sets'][4])->toBe(['home' => 0, 'away' => 15, 'winner' => 'away', 'finished' => true]);
});

it('uses the configured set points and sets to win', function () {
    $match = new SetMatch(volleyballRules(['scoring' => ['sets_to_win' => 2, 'set_points' => 21, 'deciding_set_points' => 15]]));

    expect($match->decidingSet())->toBe(3)
        ->and($match->state(rally('home', 21))['sets'][0]['finished'])->toBeTrue()
        ->and($match->state([...rally('home', 21), ...rally('home', 21)])['finished'])->toBeTrue();
});

describe('volleyball standings', function () {
    $table = fn (array $games) => (new StandingsCalculator)->calculate(['A', 'B', 'C'], $games, volleyballRules());

    it('gives 3 points for 3-0 and 3-1 and 2/1 for a 3-2', function () use ($table) {
        $rows = collect($table([
            new GameResult('A', 'B', 3, 0, false, 75, 40),
            new GameResult('B', 'C', 3, 2, false, 110, 105),
        ]))->keyBy('teamId');

        expect($rows['A']->points)->toBe(3)
            ->and($rows['B']->points)->toBe(2) // perdeu 0-3 (0) + ganhou 3-2 (2)
            ->and($rows['C']->points)->toBe(1)
            ->and($rows['A']->goalsFor)->toBe(3)
            ->and($rows['A']->scoreFor)->toBe(75);
    });

    it('breaks ties by set ratio and then by point ratio', function () use ($table) {
        // A e B: 1 vitória cada (3 pontos). A: sets 3-1 (razão 3). B: sets 3-2 e 0-... ajustado abaixo.
        $rows = $table([
            new GameResult('A', 'C', 3, 1, false, 100, 90),
            new GameResult('B', 'C', 3, 0, false, 75, 60),
        ]);

        // B ganhou 3-0 (razão infinita) e A 3-1: B fica à frente de A
        expect(array_map(fn ($row) => $row->teamId, $rows))->toBe(['B', 'A', 'C']);
    });

    it('uses the point ratio when the set ratio is equal', function () use ($table) {
        $rows = $table([
            new GameResult('A', 'C', 3, 0, false, 75, 70),
            new GameResult('B', 'C', 3, 0, false, 75, 50),
        ]);

        expect(array_map(fn ($row) => $row->teamId, $rows))->toBe(['B', 'A', 'C']);
    });
});

it('does not treat a one-goal win as narrow outside sets', function () {
    $rows = collect((new StandingsCalculator)->calculate(['A', 'B'], [new GameResult('A', 'B', 2, 1)], RuleSet::defaults()))->keyBy('teamId');

    expect($rows['A']->points)->toBe(3)->and($rows['B']->points)->toBe(0);
});
