<?php

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Support\Security\ContentSecurityPolicy;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('only reports violations by default and keeps frame-ancestors enforced', function () {
    $response = $this->get('/status.json');

    expect($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self'")
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("default-src 'self'", "object-src 'none'", 'report-uri /csp-report');
});

it('enforces the policy in the same header when asked', function () {
    config(['platform.security.csp.mode' => 'enforce']);

    $response = $this->get('/status.json');

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'", "script-src 'self' 'nonce-")
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('can be turned off and falls back to report for unknown modes', function () {
    config(['platform.security.csp.mode' => 'off']);
    $response = $this->get('/status.json');
    expect($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self'")
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();

    config(['platform.security.csp.mode' => 'banana']);
    expect(ContentSecurityPolicy::mode())->toBe('report');
});

it('keeps embeds frameable by anyone', function () {
    config(['platform.security.csp.mode' => 'enforce']);

    expect(ContentSecurityPolicy::policy('abc'))->not->toContain('frame-ancestors');
});

it('only accepts well formed extra sources', function () {
    config(['platform.security.csp.extra_sources' => "https://cdn.exemplo.com.br  ws://localhost:5173 javascript:alert(1) 'unsafe-eval' https://x.com;script-src"]);

    expect(ContentSecurityPolicy::extra())->toBe('https://cdn.exemplo.com.br ws://localhost:5173');
    expect(ContentSecurityPolicy::policy('n'))->toContain('script-src \'self\' \'nonce-n\' https://cdn.exemplo.com.br');
});

it('puts a fresh nonce on the inline theme script', function () {
    $html = $this->get('/produto')->assertOk()->getContent();

    preg_match("/'nonce-([^']+)'/", (string) $this->get('/produto')->headers->get('Content-Security-Policy-Report-Only'), $header);

    expect($html)->toMatch('/<script[^>]*nonce="[^"]+"/')->and($header[1] ?? '')->not->toBe('');
});

it('receives violation reports without login, csrf or storing anything', function () {
    Log::spy();

    $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
        'csp-report' => ['violated-directive' => 'script-src', 'blocked-uri' => 'https://evil.test/a.js?token=segredo', 'document-uri' => 'https://x.test/p?q=1'],
    ]))->assertNoContent();

    Log::shouldHaveReceived('notice')->once();
});

it('ignores reports when logging is off', function () {
    config(['platform.security.csp.log_reports' => false]);
    Log::spy();

    $this->postJson('/csp-report', ['csp-report' => []])->assertNoContent();

    Log::shouldNotHaveReceived('notice');
});
