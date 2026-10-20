<?php

use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

/**
 * Rotas de área restrita (painel, gestor, atleta, plataforma, perfil) que um visitante anônimo NUNCA pode usar.
 * Rota nova numa dessas áreas que esqueça o middleware de login falha aqui sem ninguém lembrar de escrever teste.
 *
 * @return list<array{string, string}> [método, caminho com parâmetros de mentira]
 */
function protectedRoutes(string ...$prefixes): array
{
    $found = [];

    foreach (Route::getRoutes() as $route) {
        /** @var LaravelRoute $route */
        $name = (string) $route->getName();

        if ($name === '' || ! collect($prefixes)->contains(fn (string $prefix) => str_starts_with($name, $prefix))) {
            continue;
        }

        $uri = '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '01JTESTTESTTESTTESTTESTTEST', $route->uri()), '/');
        $method = collect($route->methods())->first(fn (string $method) => ! in_array($method, ['HEAD', 'OPTIONS'], true));

        $found[] = [(string) $method, $uri];
    }

    return $found;
}

it('has routes to protect in every restricted area', function () {
    expect(protectedRoutes('panel.'))->not->toBeEmpty()
        ->and(protectedRoutes('manager.'))->not->toBeEmpty()
        ->and(protectedRoutes('athlete.'))->not->toBeEmpty()
        ->and(protectedRoutes('platform.'))->not->toBeEmpty();
});

it('never serves a restricted route to a visitor who is not logged in', function () {
    $open = [];

    foreach (protectedRoutes('panel.', 'manager.', 'athlete.', 'platform.', 'profile.', 'legal.accept.', 'password.update', 'logout') as [$method, $uri]) {
        $status = $this->call($method, $uri)->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $open[] = "{$method} {$uri} → {$status}";
        }
    }

    expect($open)->toBe([], 'rotas restritas respondendo a anônimos: '.implode('; ', $open));
});

it('keeps the panel, the platform area and the athlete area shut to a team manager on every page', function () {
    $manager = managerFor(Team::factory()->create());
    $open = [];

    foreach (protectedRoutes('panel.', 'platform.', 'athlete.') as [$method, $uri]) {
        if ($method !== 'GET') {
            continue;
        }

        $status = $this->actingAs($manager)->call($method, $uri)->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $open[] = "{$uri} → {$status}";
        }
    }

    expect($open)->toBe([], 'páginas abertas ao gestor de equipe: '.implode('; ', $open));
});

it('keeps the platform area shut to an organization administrator who is not a platform admin', function () {
    $admin = panelUser(Role::Admin);
    $open = [];

    foreach (protectedRoutes('platform.') as [$method, $uri]) {
        $status = $this->actingAs($admin)->call($method, $uri)->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $open[] = "{$method} {$uri} → {$status}";
        }
    }

    expect($admin)->toBeInstanceOf(User::class)->and($open)->toBe([], 'área da plataforma aberta a quem não é Super Admin: '.implode('; ', $open));
});

it('keeps the panel shut to an athlete on every page', function () {
    $athlete = panelUser(Role::Athlete);
    $open = [];

    foreach (protectedRoutes('panel.', 'platform.', 'manager.') as [$method, $uri]) {
        if ($method !== 'GET') {
            continue;
        }

        $status = $this->actingAs($athlete)->call($method, $uri)->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $open[] = "{$uri} → {$status}";
        }
    }

    expect($open)->toBe([], 'páginas abertas ao atleta: '.implode('; ', $open));
});

it('does not expose the Horizon dashboard to anyone outside the local environment', function () {
    $this->get('/horizon')->assertForbidden();
    $this->actingAs(panelUser(Role::Admin))->get('/horizon')->assertForbidden();
});
