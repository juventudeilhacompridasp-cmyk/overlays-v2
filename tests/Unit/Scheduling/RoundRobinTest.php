<?php

use App\Modules\Matches\Scheduling\RoundRobin;
use App\Modules\Matches\Scheduling\SnakeDistributor;

/** @return list<string> */
function teams(int $n): array
{
    return array_map(fn ($i) => "T{$i}", range(1, $n));
}

it('plays every pair exactly once in a single round robin', function (int $n) {
    $rounds = RoundRobin::rounds(teams($n));

    $pairs = [];
    foreach ($rounds as $round) {
        foreach ($round as [$home, $away]) {
            $key = collect([$home, $away])->sort()->implode('|');
            $pairs[$key] = ($pairs[$key] ?? 0) + 1;
        }
    }

    expect(count($pairs))->toBe($n * ($n - 1) / 2)
        ->and(collect($pairs)->every(fn ($times) => $times === 1))->toBeTrue()
        ->and(count($rounds))->toBe($n % 2 === 0 ? $n - 1 : $n);
})->with([2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 15, 16]);

it('never schedules a team twice in the same round', function (int $n) {
    foreach (RoundRobin::rounds(teams($n), doubleRound: true) as $round) {
        $playing = collect($round)->flatten();

        expect($playing->count())->toBe($playing->unique()->count());
    }
})->with([3, 4, 5, 8, 11]);

it('keeps home and away games balanced', function (int $n) {
    $home = array_fill_keys(teams($n), 0);
    $total = array_fill_keys(teams($n), 0);

    foreach (RoundRobin::rounds(teams($n)) as $round) {
        foreach ($round as [$h, $a]) {
            $home[$h]++;
            $total[$h]++;
            $total[$a]++;
        }
    }

    foreach ($home as $team => $homeGames) {
        $away = $total[$team] - $homeGames;
        // Número par de equipes: no máximo 1 de diferença; ímpar (com folgas): no máximo 2.
        expect(abs($homeGames - $away))->toBeLessThanOrEqual($n % 2 === 0 ? 1 : 2);
    }
})->with([4, 6, 8, 10, 12, 16, 5, 7, 9]);

it('mirrors the second leg in a double round robin with home and away swapped', function () {
    $rounds = RoundRobin::rounds(teams(4), doubleRound: true);

    expect(count($rounds))->toBe(6);

    foreach (range(0, 2) as $i) {
        $first = collect($rounds[$i]);
        $second = collect($rounds[$i + 3]);

        expect($second->map(fn ($p) => [$p[1], $p[0]])->all())->toBe($first->all());
    }

    $home = array_fill_keys(teams(4), 0);
    foreach ($rounds as $round) {
        foreach ($round as [$h]) {
            $home[$h]++;
        }
    }
    expect(array_values(array_unique($home)))->toBe([3]);
});

it('returns no rounds for fewer than two teams and rejects repeated teams', function () {
    expect(RoundRobin::rounds([]))->toBe([])
        ->and(RoundRobin::rounds(['A']))->toBe([]);

    RoundRobin::rounds(['A', 'A', 'B']);
})->throws(InvalidArgumentException::class);

it('is deterministic for the same input', function () {
    expect(RoundRobin::rounds(teams(6), true))->toBe(RoundRobin::rounds(teams(6), true));
});

it('distributes teams in a snake keeping the top seeds apart', function () {
    $groups = SnakeDistributor::distribute(teams(8), 2);

    expect($groups)->toBe([
        ['T1', 'T4', 'T5', 'T8'],
        ['T2', 'T3', 'T6', 'T7'],
    ]);

    expect(SnakeDistributor::distribute(teams(6), 3))->toBe([
        ['T1', 'T6'],
        ['T2', 'T5'],
        ['T3', 'T4'],
    ]);
});

it('keeps groups balanced when the teams do not divide evenly', function () {
    $groups = SnakeDistributor::distribute(teams(7), 3);

    expect(array_map('count', $groups))->toBe([3, 2, 2])
        ->and(collect($groups)->flatten()->sort()->values()->all())->toBe(collect(teams(7))->sort()->values()->all());
});

it('rejects invalid group counts', function () {
    SnakeDistributor::distribute(teams(3), 4);
})->throws(InvalidArgumentException::class);
