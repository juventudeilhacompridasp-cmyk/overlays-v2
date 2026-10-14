<?php

use App\Modules\Rules\Sheet\GameClock;
use App\Modules\Rules\Sheet\ShotClock;

it('runs from 24 seconds and follows pauses and resumes of the game clock', function () {
    $snapshot = (new ShotClock)->snapshot([
        ['type' => 'period_start', 'period' => 1, 'at_ms' => 1_000],
        ['type' => 'clock_pause', 'period' => 1, 'at_ms' => 6_000],
        ['type' => 'clock_resume', 'period' => 1, 'at_ms' => 10_000],
        ['type' => 'clock_pause', 'period' => 1, 'at_ms' => 17_000],
    ]);

    expect($snapshot)->toBe([
        'state' => GameClock::PAUSED,
        'period' => 1,
        'base_seconds' => 12,
        'running_since_ms' => null,
    ]);
});

it('resets to 14 or 24 seconds and ends with the period', function () {
    $snapshot = (new ShotClock)->snapshot([
        ['type' => 'period_start', 'period' => 2, 'at_ms' => 1_000],
        ['type' => 'shot_clock_reset', 'period' => 2, 'at_ms' => 8_000, 'seconds' => 14],
        ['type' => 'clock_pause', 'period' => 2, 'at_ms' => 13_000],
        ['type' => 'shot_clock_reset', 'period' => 2, 'at_ms' => 14_000, 'seconds' => 24],
        ['type' => 'clock_resume', 'period' => 2, 'at_ms' => 20_000],
        ['type' => 'period_end', 'period' => 2, 'at_ms' => 23_000],
    ]);

    expect($snapshot)->toBe([
        'state' => GameClock::STOPPED,
        'period' => 2,
        'base_seconds' => 21,
        'running_since_ms' => null,
    ]);
});
