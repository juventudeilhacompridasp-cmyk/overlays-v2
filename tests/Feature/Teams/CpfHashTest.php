<?php

use App\Modules\Teams\Support\Cpf;

it('hashes the same CPF identically regardless of punctuation and never contains it in clear', function () {
    config(['platform.document_hash_key' => 'chave-de-teste']);

    $a = Cpf::hash('529.982.247-25');
    $b = Cpf::hash('52998224725');

    expect($a)->toBe($b)
        ->and($a)->toHaveLength(64)
        ->and($a)->not->toContain('52998224725')
        ->and(Cpf::hash('111.444.777-35'))->not->toBe($a);
});

it('changes the hash when the key changes and falls back to the app key', function () {
    config(['platform.document_hash_key' => 'chave-a']);
    $a = Cpf::hash('52998224725');

    config(['platform.document_hash_key' => 'chave-b']);
    $b = Cpf::hash('52998224725');

    config(['platform.document_hash_key' => null, 'app.key' => 'base64:abc']);
    $fallback = Cpf::hash('52998224725');

    expect($a)->not->toBe($b)
        ->and($fallback)->toBe(hash_hmac('sha256', '52998224725', 'base64:abc'));
});
