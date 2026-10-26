<?php

use App\Modules\Teams\Support\DocumentValidity;
use Illuminate\Support\Carbon;

$today = fn () => Carbon::parse('2026-10-26 15:30:00');

it('classifica o documento em válido, vencendo e vencido', function () use ($today) {
    expect(DocumentValidity::status(Carbon::parse('2026-12-31'), $today(), 15))->toBe('valid')
        ->and(DocumentValidity::status(Carbon::parse('2026-11-10'), $today(), 15))->toBe('expiring')
        ->and(DocumentValidity::status(Carbon::parse('2026-10-26'), $today(), 15))->toBe('expiring')
        ->and(DocumentValidity::status(Carbon::parse('2026-10-25'), $today(), 15))->toBe('expired');
});

it('conta os dias que faltam ignorando a hora, e o documento vale até o fim do dia da validade', function () use ($today) {
    expect(DocumentValidity::daysLeft(Carbon::parse('2026-10-26 00:00:00'), $today()))->toBe(0)
        ->and(DocumentValidity::daysLeft(Carbon::parse('2026-10-31'), $today()))->toBe(5)
        ->and(DocumentValidity::daysLeft(Carbon::parse('2026-10-20'), $today()))->toBe(-6)
        ->and(DocumentValidity::validOn(Carbon::parse('2026-10-26'), Carbon::parse('2026-10-26 22:00:00')))->toBeTrue()
        ->and(DocumentValidity::validOn(Carbon::parse('2026-10-26'), Carbon::parse('2026-10-27 00:00:00')))->toBeFalse();
});

it('traduz as situações', function () {
    expect(DocumentValidity::label('expired'))->toBe('Vencido')
        ->and(DocumentValidity::label('expiring'))->toBe('Vence em breve')
        ->and(DocumentValidity::label('valid'))->toBe('Válido');
});
