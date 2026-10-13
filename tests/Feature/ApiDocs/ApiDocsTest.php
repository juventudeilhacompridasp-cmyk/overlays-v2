<?php

use App\Models\User;
use App\Modules\ApiDocs\Support\OpenApiBuilder;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    HelpLibrary::flush();
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function docsPlatformAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_platform_admin' => true])->save();

    return $user;
}

/** Valida o exemplo contra o subconjunto de JSON Schema usado no catálogo; devolve os problemas encontrados. */
function docsSchemaProblems(mixed $value, array $schema, string $path = '$'): array
{
    $problems = [];
    $types = (array) ($schema['type'] ?? []);

    if ($types !== []) {
        $actual = match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            default => 'unknown',
        };
        // Lista vazia vale como objeto vazio; inteiro vale como número.
        $accepts = in_array($actual, $types, true) || ($actual === 'integer' && in_array('number', $types, true)) || ($value === [] && in_array('object', $types, true));

        if (! $accepts) {
            return ["{$path}: esperado ".implode('|', $types).", veio {$actual}"];
        }
    }

    if (array_key_exists('enum', $schema) && ! in_array($value, $schema['enum'], true)) {
        $problems[] = "{$path}: valor fora do enum";
    }

    if (is_array($value) && ! array_is_list($value)) {
        foreach ($schema['required'] ?? [] as $key) {
            if (! array_key_exists($key, $value)) {
                $problems[] = "{$path}: falta `{$key}`";
            }
        }

        foreach ($schema['properties'] ?? [] as $key => $sub) {
            if (array_key_exists($key, $value)) {
                $problems = [...$problems, ...docsSchemaProblems($value[$key], $sub, "{$path}.{$key}")];
            }
        }
    }

    if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
        foreach ($value as $index => $item) {
            $problems = [...$problems, ...docsSchemaProblems($item, $schema['items'], "{$path}[{$index}]")];
        }
    }

    return $problems;
}

describe('the specification', function () {
    it('is a valid OpenAPI 3.1 skeleton built from the registered routes', function () {
        $spec = (new OpenApiBuilder)->build();

        expect($spec['openapi'])->toBe('3.1.0')
            ->and($spec['info']['title'])->not->toBe('')
            ->and($spec['paths'])->toHaveKey('/api/v1/competitions')
            ->and($spec['paths']['/api/v1/seasons/{season}']['get']['parameters'][0])->toMatchArray(['name' => 'season', 'in' => 'path', 'required' => true])
            ->and($spec['components']['securitySchemes'])->toHaveKeys(['session', 'overlayToken', 'personalLink']);

        foreach ($spec['paths'] as $path => $methods) {
            expect($path)->toStartWith('/');

            foreach ($methods as $method => $operation) {
                expect(['get', 'post', 'put', 'patch', 'delete', 'options'])->toContain($method)
                    ->and($operation['responses'])->not->toBeEmpty()
                    ->and($operation['tags'])->toHaveCount(1)
                    ->and(OpenApiBuilder::tagNames())->toContain($operation['tags'][0]);
            }
        }
    });

    it('classifies access, feature, permission and throttle from the route middleware', function () {
        $spec = (new OpenApiBuilder)->build();

        $publicApi = $spec['paths']['/api/v1/games/{game}']['get'];
        $sync = collect($spec['paths'])->flatten(1)->firstWhere('x-name', 'panel.games.sync');
        $overlay = collect($spec['paths'])->flatten(1)->firstWhere('x-name', 'overlay.state');
        $platform = collect($spec['paths'])->flatten(1)->firstWhere('x-name', 'platform.api-docs.index');
        $push = collect($spec['paths'])->flatten(1)->firstWhere('x-name', 'push.subscribe');

        expect($publicApi['x-access'])->toBe('Pública')
            ->and($publicApi['security'])->toBe([])
            ->and($publicApi['x-throttle'])->toBe('60,1')
            ->and($sync['x-access'])->toBe('Membro do painel (sessão)')
            ->and($sync['x-abilities'])->toContain('operate-games')
            ->and($sync['security'])->toBe([['session' => []]])
            ->and($overlay['x-access'])->toBe('Token do canal na URL')
            ->and($overlay['x-feature'])->toBe('overlays')
            ->and($platform['x-access'])->toBe('Super Admin')
            ->and($push['x-feature'])->toBe('push');
    });

    it('never leaks secrets: tokens only appear as placeholders', function () {
        $json = json_encode((new OpenApiBuilder)->build(), JSON_UNESCAPED_SLASHES);

        expect($json)->toContain('/overlay/{channel}/estado')
            ->and($json)->not->toContain(config('app.key'))
            ->and($json)->not->toContain('APP_KEY');
    });
});

describe('the catalog', function () {
    it('only describes routes that exist', function () {
        $names = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->getName())->filter()->all();

        foreach (array_keys(OpenApiBuilder::catalog()) as $name) {
            expect(in_array($name, $names, true))->toBeTrue("entrada do catálogo sem rota: {$name}");
        }
    });

    it('describes EVERY public API route (/api/*)', function () {
        $spec = (new OpenApiBuilder)->build();

        foreach ($spec['paths'] as $path => $methods) {
            if (! str_starts_with($path, '/api/')) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                expect($operation['x-documented'])->toBeTrue("{$method} {$path} sem descrição no catálogo");
                expect($operation['summary'])->not->toBe('');
                expect(array_keys($operation['responses']))->toContain('200');
            }
        }
    });

    it('has examples that satisfy their own schemas', function () {
        foreach (OpenApiBuilder::catalog() as $name => $doc) {
            foreach ($doc['responses'] ?? [] as $status => $response) {
                if (isset($response['schema'])) {
                    expect($response['example'] ?? null)->not->toBeNull("{$name} {$status}: esquema sem exemplo");
                    expect(docsSchemaProblems($response['example'], $response['schema']))->toBe([], "{$name} {$status}");
                }
            }

            if (isset($doc['request'])) {
                expect(docsSchemaProblems($doc['request']['example'], $doc['request']['schema']))->toBe([], "{$name} corpo da requisição");
            }
        }
    });

    it('documents the real routes with the middleware they actually have', function () {
        $spec = (new OpenApiBuilder)->build();
        $syncOperation = collect($spec['paths'])->flatten(1)->firstWhere('x-name', 'panel.games.sync');

        expect($syncOperation['x-documented'])->toBeTrue()
            ->and($syncOperation['requestBody']['content']['application/json']['example']['items'])->not->toBeEmpty()
            ->and(array_keys($syncOperation['responses']))->toContain('200', '423');
    });
});

describe('access', function () {
    it('is for the platform administrator only', function () {
        $this->get(route('platform.api-docs.index'))->assertRedirect();
        $this->actingAs(panelUser(Role::Admin))->get(route('platform.api-docs.index'))->assertNotFound();
        $this->actingAs(panelUser(Role::Admin))->get(route('platform.api-docs.openapi'))->assertNotFound();
    });

    it('shows the operations to the platform administrator', function () {
        $this->actingAs(docsPlatformAdmin())->get(route('platform.api-docs.index'))->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $paths = collect($props['operations'])->pluck('path');

            expect($page->toArray()['component'])->toBe('Platform/ApiDocs/Index')
                ->and($paths)->toContain('/api/v1/competitions', '/painel/games/{game}/sync')
                ->and($props['tags'])->toContain('API pública v1')
                ->and($props['documented'])->toBeGreaterThan(5);
        });
    });

    it('downloads the specification as JSON', function () {
        $response = $this->actingAs(docsPlatformAdmin())->get(route('platform.api-docs.openapi'));

        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="overlays-v2-openapi.json"');
        $spec = json_decode($response->getContent(), true);

        expect($spec['openapi'])->toBe('3.1.0')->and($spec['paths'])->toHaveKey('/api/v1/games/{game}');
    });

    it('has a help article for it, visible only on the platform help', function () {
        $article = HelpLibrary::find('documentacao-das-apis');

        expect($article)->not->toBeNull()->and($article->audience)->toBe('platform')->and(Route::has($article->route))->toBeTrue();
    });
});
