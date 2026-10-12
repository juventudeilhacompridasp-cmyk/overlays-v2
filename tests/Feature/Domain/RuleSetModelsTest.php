<?php

use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Season;
use App\Modules\Rules\Tiebreaker;

it('exposes the season regulation with reference defaults', function () {
    $season = Season::factory()->create(['rules' => ['points' => ['win' => 2]]]);

    expect($season->ruleSet()->pointsWin)->toBe(2)
        ->and($season->ruleSet()->pointsDraw)->toBe(1)
        ->and($season->ruleSet()->yellowCardsForSuspension)->toBe(3);
});

it('lets a division override the season rules', function () {
    $season = Season::factory()->create(['rules' => [
        'points' => ['win' => 2],
        'tiebreakers' => ['wins', 'draw'],
    ]]);

    $inherits = Division::factory()->for($season)->create(['rules' => null]);
    $overrides = Division::factory()->for($season)->create(['rules' => [
        'tiebreakers' => ['goal_difference'],
        'cards' => ['yellow_for_suspension' => 2],
    ]]);

    expect($inherits->ruleSet()->pointsWin)->toBe(2)
        ->and($inherits->ruleSet()->tiebreakers)->toBe([Tiebreaker::Wins, Tiebreaker::Draw])
        ->and($overrides->ruleSet()->pointsWin)->toBe(2)
        ->and($overrides->ruleSet()->tiebreakers)->toBe([Tiebreaker::GoalDifference])
        ->and($overrides->ruleSet()->yellowCardsForSuspension)->toBe(2);
});
