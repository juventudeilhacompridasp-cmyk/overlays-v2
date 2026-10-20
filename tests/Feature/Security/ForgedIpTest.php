<?php

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    Route::middleware('web')->get('/_test/ip', fn (Request $request) => $request->ip());
});

it('does not let the visitor choose his own IP with a forged X-Forwarded-For', function () {
    // O Cloudflare/NPM ACRESCENTA o IP real (203.0.113.9) ao final; o que veio antes é do cliente e não vale.
    $this->withHeaders(['X-Forwarded-For' => '6.6.6.6, 203.0.113.9'])
        ->get('/_test/ip')
        ->assertOk()
        ->assertSee('203.0.113.9')
        ->assertDontSee('6.6.6.6');
});

it('gives login lockout a key that a forged header cannot rotate', function () {
    // Mesmo e-mail, cabeçalhos "diferentes": o IP considerado é o mesmo, então a chave de bloqueio também.
    $one = $this->withHeaders(['X-Forwarded-For' => '1.1.1.1, 203.0.113.9'])->get('/_test/ip')->getContent();
    $two = $this->withHeaders(['X-Forwarded-For' => '2.2.2.2, 203.0.113.9'])->get('/_test/ip')->getContent();

    expect($one)->toBe($two)->toBe('203.0.113.9');
});
