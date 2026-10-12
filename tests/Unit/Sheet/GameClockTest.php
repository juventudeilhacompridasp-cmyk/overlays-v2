<?php

use App\Modules\Rules\Sheet\ClockMark;
use App\Modules\Rules\Sheet\GameClock;

it('is not started without marks', function () {
    expect((new GameClock)->snapshot([]))->toBe(['state' => 'not_started', 'period' => null, 'base_seconds' => 0, 'running_since_ms' => null]);
});

it('runs from the start of the first half', function () {
    $snapshot = (new GameClock)->snapshot([new ClockMark(true, 1, 1_000_000)]);

    expect($snapshot)->toBe(['state' => 'running', 'period' => 1, 'base_seconds' => 0, 'running_since_ms' => 1_000_000]);
});

it('freezes at the elapsed time when the period ends', function () {
    $snapshot = (new GameClock)->snapshot([new ClockMark(true, 1, 0), new ClockMark(false, 1, 47 * 60_000 + 30_000)]);

    expect($snapshot)->toBe(['state' => 'stopped', 'period' => 1, 'base_seconds' => 47 * 60 + 30, 'running_since_ms' => null]);
});

it('starts the second half at the nominal 45 minutes regardless of how long the first one took', function () {
    $snapshot = (new GameClock)->snapshot([
        new ClockMark(true, 1, 0), new ClockMark(false, 1, 50 * 60_000),
        new ClockMark(true, 2, 80 * 60_000),
    ]);

    expect($snapshot['state'])->toBe('running')
        ->and($snapshot['period'])->toBe(2)
        ->and($snapshot['base_seconds'])->toBe(45 * 60)
        ->and($snapshot['running_since_ms'])->toBe(80 * 60_000);
});

it('supports custom period lengths', function () {
    $clock = new GameClock([30, 30]);

    expect($clock->offsetSeconds(2))->toBe(1800)
        ->and($clock->maxPeriod())->toBe(2);
});

it('validates the order of period marks', function (array $marks, ClockMark $next, ?string $error) {
    expect((new GameClock)->check($marks, $next))->toBe($error);
})->with([
    'início do 1º tempo' => [[], new ClockMark(true, 1), null],
    'iniciar o 2º sem encerrar o 1º' => [[new ClockMark(true, 1)], new ClockMark(true, 2), 'Encerre o período anterior antes de iniciar o próximo.'],
    'iniciar o 2º depois do intervalo' => [[new ClockMark(true, 1), new ClockMark(false, 1)], new ClockMark(true, 2), null],
    'iniciar duas vezes' => [[new ClockMark(true, 1)], new ClockMark(true, 1), 'Este período já foi iniciado.'],
    'encerrar sem iniciar' => [[], new ClockMark(false, 1), 'Este período ainda não foi iniciado.'],
    'encerrar duas vezes' => [[new ClockMark(true, 1), new ClockMark(false, 1)], new ClockMark(false, 1), 'Este período já foi encerrado.'],
    'período inexistente' => [[], new ClockMark(true, 9), 'Período inválido.'],
]);
