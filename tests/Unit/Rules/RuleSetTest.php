<?php

use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Tiebreaker;

it('defaults to the reference regulation', function () {
    $rules = RuleSet::defaults();

    expect($rules->pointsWin)->toBe(3)
        ->and($rules->pointsDraw)->toBe(1)
        ->and($rules->pointsLoss)->toBe(0)
        ->and($rules->yellowCardsForSuspension)->toBe(3)
        ->and($rules->yellowSuspensionGames)->toBe(1)
        ->and($rules->redSuspensionGames)->toBe(1)
        ->and($rules->resetYellowsBetweenStages)->toBeTrue()
        ->and($rules->maxSubstitutions)->toBe(8)
        ->and($rules->maxSubstitutionWindows)->toBe(3)
        ->and($rules->tiebreakers)->toBe([
            Tiebreaker::Wins,
            Tiebreaker::GoalDifference,
            Tiebreaker::GoalsFor,
            Tiebreaker::HeadToHead,
            Tiebreaker::FewestRedCards,
            Tiebreaker::FewestYellowCards,
            Tiebreaker::Draw,
        ]);
});

it('keeps defaults for keys that were not configured', function () {
    $rules = RuleSet::fromArray(['points' => ['win' => 2], 'cards' => ['yellow_for_suspension' => 2]]);

    expect($rules->pointsWin)->toBe(2)
        ->and($rules->pointsDraw)->toBe(1)
        ->and($rules->yellowCardsForSuspension)->toBe(2)
        ->and($rules->redSuspensionGames)->toBe(1)
        ->and($rules->tiebreakers)->toBe(RuleSet::DEFAULT_TIEBREAKERS);
});

it('replaces the whole tiebreaker list instead of merging it', function () {
    $rules = RuleSet::fromArray(['tiebreakers' => ['goal_difference', 'draw']]);

    expect($rules->tiebreakers)->toBe([Tiebreaker::GoalDifference, Tiebreaker::Draw]);
});

it('round-trips through array form', function () {
    $original = RuleSet::fromArray([
        'points' => ['win' => 2, 'draw' => 1, 'loss' => 0],
        'tiebreakers' => ['head_to_head', 'goals_for'],
        'walkover' => ['winner_goals' => 1, 'loser_goals' => 0],
    ]);

    expect(RuleSet::fromArray($original->toArray()))->toEqual($original);
});

it('rejects invalid tiebreakers and values', function () {
    expect(fn () => RuleSet::fromArray(['tiebreakers' => ['coin_flip']]))
        ->toThrow(InvalidArgumentException::class, 'coin_flip')
        ->and(fn () => RuleSet::fromArray(['cards' => ['yellow_for_suspension' => 0]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => RuleSet::fromArray(['points' => ['win' => -1]]))
        ->toThrow(InvalidArgumentException::class);
});

it('leaves period minutes, team foul limit and timeouts to the sport unless the rules set them', function () {
    $rules = RuleSet::defaults();

    expect($rules->periodMinutes)->toBeNull()->and($rules->teamFoulLimit)->toBeNull()->and($rules->timeoutLimits)->toBeNull();

    $custom = RuleSet::fromArray(['periods' => ['minutes' => [12, 12, 12, 12, 5, 5, 5, 5]], 'fouls' => ['team_limit' => 4], 'timeouts' => ['limits' => ['1' => 3, '2' => 4]]]);

    expect($custom->periodMinutes)->toBe([12, 12, 12, 12, 5, 5, 5, 5])
        ->and($custom->teamFoulLimit)->toBe(4)
        ->and($custom->timeoutLimits)->toBe([1 => 3, 2 => 4])
        ->and(RuleSet::fromArray($custom->toArray()))->toEqual($custom);
});
