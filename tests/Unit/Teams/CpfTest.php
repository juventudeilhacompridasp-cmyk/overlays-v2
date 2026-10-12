<?php

use App\Modules\Teams\Support\Cpf;

it('validates CPF check digits ignoring punctuation', function (string $cpf, bool $valid) {
    expect(Cpf::isValid($cpf))->toBe($valid);
})->with([
    ['529.982.247-25', true],
    ['52998224725', true],
    ['111.444.777-35', true],
    ['111.444.777-36', false],
    ['123.456.789-00', false],
    ['111.111.111-11', false],
    ['000.000.000-00', false],
    ['1234', false],
    ['', false],
    ['abc', false],
]);

it('normalizes to digits only', function () {
    expect(Cpf::normalize(' 529.982.247-25 '))->toBe('52998224725')
        ->and(Cpf::normalize('abc'))->toBe('');
});
