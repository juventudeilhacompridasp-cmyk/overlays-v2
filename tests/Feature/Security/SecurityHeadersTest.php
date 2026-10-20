<?php

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

it('sends the basic security headers on pages', function () {
    $response = $this->get('/status.json');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()', 'microphone=()', 'geolocation=()');
});

it('keeps the clickjacking protection next to the new headers', function () {
    $response = $this->get('/status.json');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
});

it('sends HSTS only over HTTPS and only when enabled', function () {
    config(['platform.security.hsts_seconds' => 15552000]);

    $this->get('/status.json')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/status.json')->assertHeader('Strict-Transport-Security', 'max-age=15552000');

    config(['platform.security.hsts_seconds' => 0]);
    $this->get('https://localhost/status.json')->assertHeaderMissing('Strict-Transport-Security');
});

it('sends the security headers on the API too', function () {
    $this->getJson('/api/v1/competitions')->assertHeader('X-Content-Type-Options', 'nosniff');
});
