<?php

use App\Modules\Rules\Discipline\DisciplineTracker;
use App\Modules\Rules\Discipline\TeamGameRecord;
use App\Modules\Rules\RuleSet;

function game(string $id, array $cards = [], string $stage = 's1', bool $served = true): TeamGameRecord
{
    return new TeamGameRecord($id, $stage, $served, $cards);
}

it('suspends a player for one game after three yellow cards', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['yellow' => 1]]));
    $tracker->apply(game('g2', ['P' => ['yellow' => 1]]));

    expect($tracker->isSuspended('P'))->toBeFalse()
        ->and($tracker->seriesYellows('P'))->toBe(2);

    $tracker->apply(game('g3', ['P' => ['yellow' => 1]]));

    expect($tracker->isSuspended('P'))->toBeTrue()
        ->and($tracker->pendingGames('P'))->toBe(1)
        ->and($tracker->seriesYellows('P'))->toBe(0);

    $tracker->apply(game('g4'));

    expect($tracker->isSuspended('P'))->toBeFalse();
});

it('suspends a player for one game after a red card', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['red' => 1]]));

    expect($tracker->isSuspended('P'))->toBeTrue()
        ->and($tracker->pendingGames('P'))->toBe(1);

    $tracker->apply(game('g2'));

    expect($tracker->isSuspended('P'))->toBeFalse();
});

it('treats two yellows in the same game as an expulsion that does not count for the series', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['yellow' => 2]]));

    expect($tracker->pendingGames('P'))->toBe(1)
        ->and($tracker->seriesYellows('P'))->toBe(0);
});

it('can count the second yellow for the series when the rule says so', function () {
    $tracker = new DisciplineTracker(RuleSet::fromArray(['cards' => ['second_yellow_counts_for_series' => true]]));

    $tracker->apply(game('g1', ['P' => ['yellow' => 2]]));

    expect($tracker->pendingGames('P'))->toBe(1)
        ->and($tracker->seriesYellows('P'))->toBe(2);
});

it('gives two games when the third yellow comes together with a red card', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['yellow' => 1]]));
    $tracker->apply(game('g2', ['P' => ['yellow' => 1]]));
    $tracker->apply(game('g3', ['P' => ['yellow' => 1, 'red' => 1]]));

    expect($tracker->pendingGames('P'))->toBe(2);

    $tracker->apply(game('g4'));
    expect($tracker->pendingGames('P'))->toBe(1);

    $tracker->apply(game('g5'));
    expect($tracker->isSuspended('P'))->toBeFalse();
});

it('resets accumulated yellows when the stage changes', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['yellow' => 1]], 's1'));
    $tracker->apply(game('g2', ['P' => ['yellow' => 1]], 's1'));
    $tracker->apply(game('g3', ['P' => ['yellow' => 1]], 's2'));

    expect($tracker->seriesYellows('P'))->toBe(1)
        ->and($tracker->isSuspended('P'))->toBeFalse();
});

it('keeps accumulating across stages when the reset is disabled', function () {
    $tracker = new DisciplineTracker(RuleSet::fromArray(['cards' => ['reset_yellows_between_stages' => false]]));

    $tracker->apply(game('g1', ['P' => ['yellow' => 1]], 's1'));
    $tracker->apply(game('g2', ['P' => ['yellow' => 1]], 's1'));
    $tracker->apply(game('g3', ['P' => ['yellow' => 1]], 's2'));

    expect($tracker->isSuspended('P'))->toBeTrue();
});

it('carries a suspension earned in the last game of a stage into the next stage', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['yellow' => 1]], 's1'));
    $tracker->apply(game('g2', ['P' => ['yellow' => 1]], 's1'));
    $tracker->apply(game('g3', ['P' => ['yellow' => 1]], 's1')); // 3º amarelo na última partida da fase

    expect($tracker->isSuspended('P'))->toBeTrue();

    $tracker->apply(game('g4', [], 's2'));

    expect($tracker->isSuspended('P'))->toBeFalse()
        ->and($tracker->seriesYellows('P'))->toBe(0);
});

it('counts a walkover as served but not a postponed game', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->apply(game('g1', ['P' => ['red' => 1]]));
    $tracker->apply(game('g2', served: false)); // adiado

    expect($tracker->isSuspended('P'))->toBeTrue();

    $tracker->apply(game('g3', served: true)); // W.O. cumpre a pena

    expect($tracker->isSuspended('P'))->toBeFalse();
});

it('tracks players independently and lists the suspended ones', function () {
    $tracker = new DisciplineTracker(RuleSet::defaults());

    $tracker->applyAll([
        game('g1', ['P' => ['red' => 1], 'Q' => ['yellow' => 1]]),
        game('g2', ['Q' => ['yellow' => 1]]),
    ]);

    expect($tracker->suspended())->toBe([])
        ->and($tracker->seriesYellows('Q'))->toBe(2)
        ->and($tracker->isSuspended('P'))->toBeFalse();
});

it('uses custom thresholds from the rule set', function () {
    $tracker = new DisciplineTracker(RuleSet::fromArray(['cards' => [
        'yellow_for_suspension' => 2,
        'yellow_suspension_games' => 2,
        'red_suspension_games' => 3,
    ]]));

    $tracker->apply(game('g1', ['P' => ['yellow' => 1]]));
    $tracker->apply(game('g2', ['P' => ['yellow' => 1], 'Q' => ['red' => 1]]));

    expect($tracker->pendingGames('P'))->toBe(2)
        ->and($tracker->pendingGames('Q'))->toBe(3);
});
