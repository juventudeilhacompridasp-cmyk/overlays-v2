<?php

use App\Support\TrustedProxies;

it('trusts only private addresses by default, never everything', function () {
    expect(TrustedProxies::resolve(null))->toBe(TrustedProxies::PRIVATE_RANGES)
        ->and(TrustedProxies::resolve('  '))->toBe(TrustedProxies::PRIVATE_RANGES)
        ->and(TrustedProxies::resolve(null))->not->toContain('*')
        ->and(TrustedProxies::PRIVATE_RANGES)->toContain('172.16.0.0/12', '10.0.0.0/8', '192.168.0.0/16');
});

it('reads a comma separated list and keeps an explicit wildcard only when asked', function () {
    expect(TrustedProxies::resolve('10.1.2.3, 172.20.0.0/16 ,,'))->toBe(['10.1.2.3', '172.20.0.0/16'])
        ->and(TrustedProxies::resolve('*'))->toBe('*')
        ->and(TrustedProxies::resolve(' , '))->toBe(TrustedProxies::PRIVATE_RANGES);
});
