<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Rules\RuleSet;
use App\Modules\Rules\Sheet\ClockMark;
use App\Modules\Rules\Sports\SportProfiles;

it('keeps football with the reference defaults', function () {
    $profile = Sport::Football->profile();

    expect($profile->periodMinutes)->toBe([45, 45, 15, 15])
        ->and($profile->tracksTeamFouls())->toBeFalse()
        ->and($profile->defaultRules()->toArray())->toBe(RuleSet::defaults()->toArray())
        ->and($profile->clock()->offsetSeconds(2))->toBe(45 * 60);
});

it('defines futsal with 5 on court, unlimited rolling substitutions and 20-minute halves', function () {
    $profile = Sport::Futsal->profile();
    $rules = $profile->defaultRules();

    expect($rules->maxStarters)->toBe(5)
        ->and($rules->maxBench)->toBe(9)
        ->and($rules->maxSubstitutions)->toBe(99)
        ->and($rules->maxSubstitutionWindows)->toBe(99)
        ->and($rules->pointsWin)->toBe(3)
        ->and($profile->clock()->offsetSeconds(2))->toBe(20 * 60)
        ->and($profile->clock()->offsetSeconds(3))->toBe(40 * 60)
        ->and($profile->clock()->maxPeriod())->toBe(4)
        ->and($profile->teamFoulLimit)->toBe(5);
});

it('lets the saved rules win over the sport defaults', function () {
    $rules = RuleSet::fromArray(['lineup' => ['max_starters' => 6]], Sport::Futsal->profile()->ruleDefaults);

    expect($rules->maxStarters)->toBe(6)->and($rules->maxBench)->toBe(9);
});

it('resets fouls each regulation half and carries the second half into extra time', function () {
    $profile = Sport::Futsal->profile();

    expect([$profile->foulGroup(1), $profile->foulGroup(2), $profile->foulGroup(3), $profile->foulGroup(4)])->toBe([1, 2, 2, 2]);
});

it('uses the futsal clock to validate periods', function () {
    $clock = Sport::Futsal->profile()->clock();

    expect($clock->check([], new ClockMark(true, 5)))->toBe('Período inválido.');
});

it('refuses a sport without profile', function () {
    expect(fn () => SportProfiles::for('curling'))->toThrow(InvalidArgumentException::class);
});

it('defines basketball with FIBA-style quarters, fouls and table points', function () {
    $profile = Sport::Basketball->profile();
    $rules = $profile->defaultRules();

    expect($profile->scoreModel)->toBe('points')
        ->and($profile->pausableClock)->toBeTrue()
        ->and($profile->clockDirection)->toBe('down')
        ->and($profile->clock()->maxPeriod())->toBe(8)
        ->and($profile->clock()->periodMinutes(1))->toBe(10)
        ->and($profile->clock()->periodMinutes(5))->toBe(5)
        ->and($profile->teamFoulLimit)->toBe(5)
        ->and($rules->maxPlayerFouls)->toBe(5)
        ->and($rules->maxStarters)->toBe(5)
        ->and($rules->maxBench)->toBe(7)
        ->and($rules->pointsWin)->toBe(2)
        ->and($rules->pointsLoss)->toBe(1)
        ->and($rules->pointsWalkoverLoss)->toBe(0)
        ->and($rules->walkoverWinnerGoals)->toBe(20)
        ->and($rules->scoreModel)->toBe('points')
        ->and(array_map(fn ($t) => $t->value, $rules->tiebreakers))->toBe(['head_to_head', 'goal_difference', 'goals_for', 'draw']);
});

it('groups basketball fouls by quarter and carries overtime into the fourth', function () {
    $profile = Sport::Basketball->profile();

    expect(array_map($profile->foulGroup(...), [1, 2, 3, 4, 5, 8]))->toBe([1, 2, 3, 4, 4, 4]);
});
