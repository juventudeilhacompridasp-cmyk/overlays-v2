<?php

use App\Modules\Matches\Scheduling\KnockoutBracket;
use App\Modules\Matches\Scheduling\KnockoutTie;
use App\Modules\Matches\Scheduling\TieResolver;

/** @return list<string> */
function bracketTeams(int $n): array
{
    return array_map(fn (int $i) => "T{$i}", range(1, $n));
}

/** @param list<KnockoutTie> $ties */
function tiesOfRound(array $ties, int $round): array
{
    return array_values(array_filter($ties, fn (KnockoutTie $t) => $t->round === $round));
}

it('pairs seeds the classic way so the two best only meet in the final', function () {
    $ties = tiesOfRound(KnockoutBracket::build(bracketTeams(8)), 1);

    expect(array_map(fn (KnockoutTie $t) => [$t->a, $t->b], $ties))->toBe([['T1', 'T8'], ['T4', 'T5'], ['T2', 'T7'], ['T3', 'T6']]);
});

it('builds every round of a power-of-two bracket', function (int $teams, array $tiesPerRound) {
    $ties = KnockoutBracket::build(bracketTeams($teams));

    foreach ($tiesPerRound as $round => $count) {
        expect(tiesOfRound($ties, $round + 1))->toHaveCount($count);
    }

    expect($ties)->toHaveCount(array_sum($tiesPerRound));
})->with([
    '2 equipes' => [2, [1]],
    '4 equipes' => [4, [2, 1]],
    '8 equipes' => [8, [4, 2, 1]],
    '16 equipes' => [16, [8, 4, 2, 1]],
]);

it('points every tie to the next round, alternating slots, and the final to none', function () {
    $ties = KnockoutBracket::build(bracketTeams(8));
    $first = tiesOfRound($ties, 1);

    expect([$first[0]->nextPosition, $first[0]->nextSlot])->toBe([0, 'a'])
        ->and([$first[1]->nextPosition, $first[1]->nextSlot])->toBe([0, 'b'])
        ->and([$first[2]->nextPosition, $first[2]->nextSlot])->toBe([1, 'a'])
        ->and([$first[3]->nextPosition, $first[3]->nextSlot])->toBe([1, 'b']);

    $final = tiesOfRound($ties, 3)[0];
    expect([$final->nextPosition, $final->nextSlot])->toBe([null, null]);
});

it('gives byes to the best seeds and places them in the second round', function () {
    $ties = KnockoutBracket::build(bracketTeams(6));
    $first = tiesOfRound($ties, 1);
    $second = tiesOfRound($ties, 2);

    // Tamanho 8: seeds 7 e 8 não existem → T2 e T1 folgam.
    expect(array_values(array_filter($first, fn (KnockoutTie $t) => $t->bye)))->toHaveCount(2)
        ->and($first[0]->bye)->toBeTrue()   // T1 x —
        ->and($first[0]->a)->toBe('T1')
        ->and($first[2]->bye)->toBeTrue()   // T2 x —
        ->and($second[0]->a)->toBe('T1')    // T1 já aparece na semifinal
        ->and($second[1]->a)->toBe('T2')
        ->and($second[0]->b)->toBeNull();
});

it('handles an odd number of teams', function () {
    $ties = KnockoutBracket::build(bracketTeams(5));

    $realFirstRound = array_filter(tiesOfRound($ties, 1), fn (KnockoutTie $t) => ! $t->bye);
    expect($realFirstRound)->toHaveCount(1)
        ->and(KnockoutBracket::rounds(5))->toBe(3);
});

it('never gives two byes in the same tie', function (int $teams) {
    foreach (tiesOfRound(KnockoutBracket::build(bracketTeams($teams)), 1) as $tie) {
        expect($tie->a !== null || $tie->b !== null)->toBeTrue();
    }
})->with(range(3, 33));

it('needs at least two teams', function () {
    KnockoutBracket::build(['A']);
})->throws(InvalidArgumentException::class);

it('names the rounds from the final backwards', function () {
    expect(KnockoutBracket::roundLabel(3, 3))->toBe('Final')
        ->and(KnockoutBracket::roundLabel(3, 2))->toBe('Semifinal')
        ->and(KnockoutBracket::roundLabel(3, 1))->toBe('Quartas de final')
        ->and(KnockoutBracket::roundLabel(4, 1))->toBe('Oitavas de final')
        ->and(KnockoutBracket::roundLabel(5, 1))->toBe('16 avos de final');
});

it('resolves a single-leg tie by score and then by penalties', function () {
    $leg = fn (int $h, int $a, ?int $hp = null, ?int $ap = null) => ['home' => 'X', 'away' => 'Y', 'home_score' => $h, 'away_score' => $a, 'home_penalties' => $hp, 'away_penalties' => $ap];

    expect(TieResolver::winner([$leg(2, 1)], 1))->toBe('X')
        ->and(TieResolver::winner([$leg(0, 3)], 1))->toBe('Y')
        ->and(TieResolver::winner([$leg(1, 1)], 1))->toBeNull()
        ->and(TieResolver::winner([$leg(1, 1, 3, 4)], 1))->toBe('Y')
        ->and(TieResolver::winner([$leg(1, 1, 5, 5)], 1))->toBeNull()
        ->and(TieResolver::winner([], 1))->toBeNull();
});

it('resolves a two-leg tie by aggregate, with penalties from the last leg', function () {
    $leg = fn (string $h, string $a, int $hs, int $as, ?int $hp = null, ?int $ap = null) => ['home' => $h, 'away' => $a, 'home_score' => $hs, 'away_score' => $as, 'home_penalties' => $hp, 'away_penalties' => $ap];

    expect(TieResolver::winner([$leg('X', 'Y', 2, 0)], 2))->toBeNull()
        ->and(TieResolver::winner([$leg('X', 'Y', 2, 0), $leg('Y', 'X', 2, 0)], 2))->toBeNull()
        ->and(TieResolver::winner([$leg('X', 'Y', 3, 0), $leg('Y', 'X', 2, 0)], 2))->toBe('X')
        ->and(TieResolver::winner([$leg('X', 'Y', 1, 0), $leg('Y', 'X', 1, 0, 4, 2)], 2))->toBe('Y');
});
