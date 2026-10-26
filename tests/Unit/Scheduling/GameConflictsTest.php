<?php

use App\Modules\Matches\Scheduling\GameConflicts;

function conflictGame(string $id, string $at, ?string $venue, array $teams, ?string $label = null): array
{
    return ['id' => $id, 'at' => strtotime($at.' UTC'), 'venue' => $venue, 'teams' => $teams, 'label' => $label ?? $id];
}

it('flags the same venue when the games are closer than the venue window, on both games', function () {
    $found = GameConflicts::find([
        conflictGame('g1', '2026-05-10 16:00', 'v1', ['a', 'b']),
        conflictGame('g2', '2026-05-10 17:30', 'v1', ['c', 'd']),
        conflictGame('g3', '2026-05-10 18:00', 'v1', ['e', 'f']),
    ]);

    expect(array_keys($found))->toBe(['g1', 'g2', 'g3'])
        ->and($found['g2'])->toHaveCount(2)
        ->and(collect($found['g1'])->pluck('kind')->unique()->all())->toBe(['venue'])
        ->and($found['g1'][0]['with'])->toBe('g2');
});

it('does not flag the same venue far enough apart or different venues', function () {
    expect(GameConflicts::find([
        conflictGame('g1', '2026-05-10 16:00', 'v1', ['a', 'b']),
        conflictGame('g2', '2026-05-10 18:00', 'v1', ['c', 'd']),
        conflictGame('g3', '2026-05-10 16:30', 'v2', ['e', 'f']),
        conflictGame('g4', '2026-05-10 16:30', null, ['g', 'h']),
        conflictGame('g5', '2026-05-10 16:30', null, ['i', 'j']),
    ]))->toBe([]);
});

it('flags a team playing twice without enough rest', function () {
    $found = GameConflicts::find([
        conflictGame('g1', '2026-05-10 16:00', 'v1', ['a', 'b']),
        conflictGame('g2', '2026-05-10 18:30', 'v2', ['a', 'c']),
        conflictGame('g3', '2026-05-10 22:00', 'v2', ['a', 'd']),
    ]);

    expect(array_keys($found))->toBe(['g1', 'g2'])
        ->and($found['g1'][0]['kind'])->toBe('team');
});

it('reports only the focus games and ignores empty teams', function () {
    $found = GameConflicts::find([
        conflictGame('g1', '2026-05-10 16:00', 'v1', []),
        conflictGame('g2', '2026-05-10 16:30', 'v1', []),
        conflictGame('g3', '2026-05-10 16:45', 'v9', []),
    ], ['g2']);

    expect(array_keys($found))->toBe(['g2'])
        ->and($found['g2'][0]['with'])->toBe('g1');
});

it('handles games out of order and none', function () {
    expect(GameConflicts::find([]))->toBe([]);

    $found = GameConflicts::find([
        conflictGame('late', '2026-05-10 17:00', 'v1', ['a']),
        conflictGame('early', '2026-05-10 16:00', 'v1', ['b']),
    ]);

    expect(array_keys($found))->toEqualCanonicalizing(['late', 'early']);
});

it('uses configured windows for venue and team warnings', function () {
    $found = GameConflicts::find([
        conflictGame('g1', '2026-05-10 16:00', 'v1', ['b']),
        conflictGame('g2', '2026-05-10 16:30', 'v1', ['a']),
        conflictGame('g3', '2026-05-10 17:30', 'v2', ['a']),
    ], null, 45, 90);

    expect(collect($found['g1'])->pluck('kind')->all())->toBe(['venue'])
        ->and(collect($found['g2'])->pluck('kind')->all())->toBe(['venue', 'team'])
        ->and(collect($found['g3'])->pluck('kind')->all())->toBe(['team']);
});
