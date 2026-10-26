<?php

use App\Support\Auth\Totp;

// Vetores de teste da RFC 6238 (SHA-1, segredo ASCII "12345678901234567890", 8 dígitos).
$secret = fn () => Totp::base32Encode('12345678901234567890');

it('gera os códigos dos vetores de teste da RFC 6238', function () use ($secret) {
    foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037'] as $time => $expected) {
        expect(Totp::code($secret(), $time, null, 8))->toBe($expected);
    }
});

it('usa 6 dígitos por padrão', function () use ($secret) {
    expect(Totp::code($secret(), 59))->toBe('287082');
});

it('confere o código com tolerância de um passo e devolve o passo em que bateu', function () use ($secret) {
    expect(Totp::verify($secret(), '287082', 59))->toBe(1)
        ->and(Totp::verify($secret(), '287082', 59 + 30))->toBe(1)
        ->and(Totp::verify($secret(), '287082', 59 + 60))->toBeNull()
        ->and(Totp::verify($secret(), '000000', 59))->toBeNull()
        ->and(Totp::verify($secret(), '28708', 59))->toBeNull()
        ->and(Totp::verify($secret(), '287 082', 59))->toBe(1);
});

it('gera segredos Base32 de 32 caracteres e converte de ida e volta', function () {
    $secret = Totp::generateSecret();

    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and(Totp::base32Encode(Totp::base32Decode($secret)))->toBe($secret)
        ->and(Totp::base32Decode('GEZDGNBV'))->toBe('123456');
});

it('monta o endereço otpauth lido pelos aplicativos', function () {
    $uri = Totp::uri('Liga & Cia', 'ana@example.com', 'ABCDEFGH');

    expect($uri)->toStartWith('otpauth://totp/Liga%20%26%20Cia:ana%40example.com?secret=ABCDEFGH')
        ->and($uri)->toContain('issuer=Liga%20%26%20Cia')
        ->and($uri)->toContain('digits=6')
        ->and($uri)->toContain('period=30');
});
