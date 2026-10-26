<?php

use App\Modules\Matches\Scheduling\OfficialConflicts;

$row = fn (string $official, string $game, int $at, string $label = 'Outro jogo') => ['official' => $official, 'name' => 'Fulano', 'game' => $game, 'at' => $at, 'label' => $label];

it('avisa quando a mesma pessoa está em dois jogos muito próximos', function () use ($row) {
    $conflicts = OfficialConflicts::find([$row('a', 'g1', 0, 'G1'), $row('a', 'g2', 3600, 'G2')], ['g1', 'g2']);

    expect($conflicts['g1'])->toBe(['Fulano também está escalado em G2.'])
        ->and($conflicts['g2'])->toBe(['Fulano também está escalado em G1.']);
});

it('não avisa quando há tempo de sobra, pessoas diferentes ou o jogo não está na tela', function () use ($row) {
    expect(OfficialConflicts::find([$row('a', 'g1', 0), $row('a', 'g2', 150 * 60)], ['g1', 'g2']))->toBe([])
        ->and(OfficialConflicts::find([$row('a', 'g1', 0), $row('b', 'g2', 60)], ['g1', 'g2']))->toBe([])
        ->and(OfficialConflicts::find([$row('a', 'g1', 0), $row('a', 'g2', 60)], ['g1']))->toHaveKey('g1')
        ->and(OfficialConflicts::find([$row('a', 'g1', 0), $row('a', 'g2', 60)], ['g1']))->not->toHaveKey('g2');
});

it('não repete o mesmo aviso', function () use ($row) {
    $conflicts = OfficialConflicts::find([$row('a', 'g1', 0), $row('a', 'g2', 60, 'G2'), $row('a', 'g2', 120, 'G2')], ['g1']);

    expect($conflicts['g1'])->toHaveCount(1);
});
