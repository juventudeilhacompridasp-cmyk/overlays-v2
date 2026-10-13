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
