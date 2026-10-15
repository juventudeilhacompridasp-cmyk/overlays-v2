<?php

use App\Models\User;
use App\Modules\ApiDocs\Support\ApiChangelog;
use App\Modules\ApiDocs\Support\OpenApiBuilder;
use App\Modules\ApiDocs\Support\PostmanExporter;
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
            ->and($spec['components']['securitySchemes'])->toHaveKeys(['session', 'overlayToken', 'overlayControlToken', 'personalLink']);

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
        $overlayControl = collect($spec['paths'])->flatten(1)->firstWhere('x-name', 'api.v1.overlay-channels.state.update');
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
            ->and($overlayControl['x-access'])->toBe('Chave Bearer do canal')
            ->and($overlayControl['security'])->toBe([['overlayControlToken' => []]])
            ->and($overlayControl['tags'])->toBe(['Overlays (OBS)'])
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
                expect(array_map('strval', array_keys($operation['responses'])))->toContain('200');
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
            ->and(array_map('strval', array_keys($syncOperation['responses'])))->toContain('200', '423');
    });
});

describe('the groups that were described in part 2', function () {
    it('describes every overlay, widget and real-time route (new route = new catalog entry)', function () {
        $spec = (new OpenApiBuilder)->build();
        $groups = ['Overlays (OBS)', 'Widgets', 'Tempo real e avisos'];
        $seen = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (in_array($operation['tags'][0], $groups, true)) {
                    $seen[$operation['tags'][0]] = true;
                    expect($operation['x-documented'])->toBeTrue("{$method} {$path} sem descrição no catálogo");
                }
            }
        }

        expect(array_keys($seen))->toEqualCanonicalizing($groups);
    });
});

describe('the internal routes (part 3)', function () {
    it('describes every panel, manager and athlete route (new screen or action = new entry in internal.php)', function () {
        $spec = (new OpenApiBuilder)->build();
        $groups = ['Painel', 'Gestor de equipe', 'Atleta (Mercado)', 'Plataforma'];
        $seen = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                // Só as rotas de sessão (panel./manager./athlete.); o cadastro público do Mercado (market.*) fica de fora.
                if (in_array($operation['tags'][0], $groups, true) && preg_match('/^(panel|manager|athlete|platform)\./', (string) $operation['x-name']) === 1) {
                    $seen[$operation['tags'][0]] = true;
                    expect($operation['x-documented'])->toBeTrue("{$method} {$path} sem descrição em resources/api-docs/internal.php");
                    expect($operation['summary'])->not->toBe($operation['x-name']);
                }
            }
        }

        expect(array_keys($seen))->toEqualCanonicalizing($groups);
    });

    it('keeps the path parameters described', function () {
        $operations = OpenApiBuilder::operations((new OpenApiBuilder)->build());

        foreach ($operations as $operation) {
            if (in_array($operation['tags'][0], ['Painel', 'Gestor de equipe', 'Atleta (Mercado)', 'Plataforma'], true) && preg_match('/^(panel|manager|athlete|platform)\./', (string) ($operation['x-name'] ?? '')) === 1) {
                foreach ($operation['parameters'] as $parameter) {
                    expect($parameter['description'])->not->toBeNull("{$operation['path']}: parâmetro {$parameter['name']} sem descrição");
                }
            }
        }
    });
});

describe('the site routes (part 4)', function () {
    it('describes every portal, status, landing, registration, signup and auth route (new route = new entry in site.php)', function () {
        $operations = OpenApiBuilder::operations((new OpenApiBuilder)->build());
        $pattern = '/^(portal|public|status|marketing|registration|market\.signup|password|verification|legal\.accept|profile)\.|^(login|logout|register|dashboard)$/';
        $seen = 0;

        foreach ($operations as $operation) {
            $name = (string) ($operation['x-name'] ?? '');

            if (preg_match($pattern, $name) === 1) {
                $seen++;
                expect($operation['x-documented'])->toBeTrue("{$operation['method']} {$operation['path']} ({$name}) sem descrição em resources/api-docs/site.php");
                expect($operation['summary'])->not->toBe($name);

                foreach ($operation['parameters'] as $parameter) {
                    expect($parameter['description'])->not->toBeNull("{$operation['path']}: parâmetro {$parameter['name']} sem descrição");
                }
            }
        }

        expect($seen)->toBeGreaterThan(40);
    });
});

describe('the change history', function () {
    it('is newest first, well formed, and gives the version of the specification', function () {
        $entries = ApiChangelog::entries();
        $versions = array_column($entries, 'version');

        expect($entries)->not->toBeEmpty()->and($versions)->toBe(array_values(array_unique($versions)))
            ->and((new OpenApiBuilder)->build()['info']['version'])->toBe($versions[0]);

        foreach ($entries as $entry) {
            expect($entry['version'])->toMatch('/^\d+\.\d+\.\d+$/')
                ->and($entry['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
                ->and($entry['changes'])->not->toBeEmpty();
        }

        $sorted = $versions;
        usort($sorted, fn (string $a, string $b) => version_compare($b, $a));
        expect($versions)->toBe($sorted);

        $dates = array_column($entries, 'date');
        $descending = $dates;
        rsort($descending);
        expect($dates)->toBe($descending);
    });
});

describe('the Postman collection', function () {
    it('has one request per operation, grouped by tag, with the base url and path variables', function () {
        $spec = (new OpenApiBuilder)->build();
        $collection = PostmanExporter::fromSpec($spec);
        $requests = collect($collection['item'])->flatMap(fn (array $folder) => $folder['item']);

        expect($collection['info']['schema'])->toBe(PostmanExporter::SCHEMA)
            ->and($collection['info']['version'])->toBe(ApiChangelog::current())
            ->and(collect($collection['variable'])->pluck('key')->all())->toContain('baseUrl', 'controlKey')
            ->and($requests->count())->toBe(count(OpenApiBuilder::operations($spec)));

        $game = $requests->first(fn (array $item) => $item['request']['url']['raw'] === '{{baseUrl}}/api/v1/games/:game' && $item['request']['method'] === 'GET');

        expect($game)->not->toBeNull()
            ->and($game['request']['url']['path'])->toBe(['api', 'v1', 'games', ':game'])
            ->and($game['request']['url']['variable'][0]['key'])->toBe('game');
    });

    it('sends the catalog example as the body and uses a Bearer variable (never the real key) for the channel control', function () {
        $collection = PostmanExporter::fromSpec((new OpenApiBuilder)->build());
        $requests = collect($collection['item'])->flatMap(fn (array $folder) => $folder['item']);
        $control = $requests->first(fn (array $item) => str_contains($item['request']['url']['raw'], 'overlay-channels') && $item['request']['method'] === 'PATCH');

        expect($control['request']['auth']['bearer'][0]['value'])->toBe('{{controlKey}}')
            ->and($control['request']['body']['mode'])->toBe('raw')
            ->and(json_decode($control['request']['body']['raw'], true))->toBeArray();
    });

    it('is downloadable only by the platform administrator', function () {
        $this->actingAs(panelUser(Role::Admin))->get(route('platform.api-docs.postman'))->assertNotFound();

        $response = $this->actingAs(docsPlatformAdmin())->get(route('platform.api-docs.postman'));

        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="overlays-v2.postman_collection.json"');
        expect(json_decode($response->getContent(), true)['info']['schema'])->toBe(PostmanExporter::SCHEMA);
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
