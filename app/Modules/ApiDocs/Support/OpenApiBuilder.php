<?php

namespace App\Modules\ApiDocs\Support;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * Monta a especificação OpenAPI 3.1 das rotas registradas (ADR 0007). A estrutura (método, caminho, parâmetros, acesso,
 * middleware, recurso do pacote, permissão) vem das próprias rotas; o texto (resumo, corpo, respostas, exemplos) vem do
 * catálogo `resources/api-docs/catalog.php`, indexado pelo NOME da rota. Rota sem entrada no catálogo aparece como
 * "sem descrição" (`x-documented: false`); entrada de catálogo para rota que não existe mais é erro no teste.
 *
 * @phpstan-type Operation array<string, mixed>
 */
final class OpenApiBuilder
{
    /** Rotas de infraestrutura que não fazem parte da superfície da aplicação. */
    private const SKIP_PREFIXES = ['horizon', '_ignition', 'storage', 'up', 'sanctum', 'broadcasting', 'livewire', 'telescope', '_debugbar'];

    private const PARAMETERS = [
        'game' => 'ID (ULID) do jogo.',
        'season' => 'ID (ULID) da edição.',
        'team' => 'ID (ULID) da equipe.',
        'entry' => 'ID (ULID) da inscrição da equipe na divisão.',
        'division' => 'ID (ULID) da divisão.',
        'stage' => 'ID (ULID) da fase.',
        'competition' => 'ID (ULID) ou slug da competição.',
        'channel' => 'Token secreto do canal de overlay.',
        'overlay' => 'Nome da tela do overlay (placar, eventos, escalacao ou tabela).',
        'token' => 'Token pessoal da inscrição (enviado por e-mail).',
        'upload' => 'ID (ULID) da súmula enviada.',
        'organization' => 'ID (ULID) da organização.',
        'membership' => 'ID (ULID) da participação do usuário na organização.',
        'profile' => 'ID (ULID) do perfil de acesso.',
        'slug' => 'Identificador (slug) do artigo de ajuda.',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalog(): array
    {
        /** @var array<string, array<string, mixed>> $catalog */
        $catalog = require resource_path('api-docs/catalog.php');

        return $catalog;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $catalog = self::catalog();
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');

            if ($this->skip($route, $uri)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $paths[$this->path($uri)][strtolower($method)] = $this->operation($route, $method, $uri, $catalog[$route->getName()] ?? null);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Overlays v2 — referência interna das rotas',
                'version' => ApiChangelog::current(),
                'description' => 'Gerada das rotas registradas. Documento INTERNO (só Super Admin): revela a superfície da aplicação. A API pública estável é `/api/v1`.',
            ],
            'servers' => [['url' => (string) config('app.url')]],
            'tags' => array_map(fn (string $name) => ['name' => $name], self::tagNames()),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'session' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'laravel_session', 'description' => 'Sessão de quem fez login (painel, gestor ou plataforma).'],
                    'overlayToken' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'URL', 'description' => 'O token do canal vai no próprio endereço (`/overlay/{token}/...`) e é o segredo.'],
                    'overlayControlToken' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Chave privada de controle do canal, enviada no cabeçalho `Authorization: Bearer ...`.'],
                    'personalLink' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'URL', 'description' => 'O token pessoal da inscrição vai no próprio endereço (`/minha-inscricao/{token}`).'],
                ],
            ],
        ];
    }

    /**
     * Lista plana das operações para a tela.
     *
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    public static function operations(array $spec): array
    {
        $list = [];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $list[] = ['method' => strtoupper($method), 'path' => $path] + $operation;
            }
        }

        usort($list, fn (array $a, array $b) => [array_search($a['tags'][0], self::tagNames()), $a['path'], $a['method']] <=> [array_search($b['tags'][0], self::tagNames()), $b['path'], $b['method']]);

        return $list;
    }

    /**
     * @return list<string>
     */
    public static function tagNames(): array
    {
        return ['API pública v1', 'Tempo real e avisos', 'Portal público', 'Inscrição online', 'Widgets', 'Overlays (OBS)', 'Painel', 'Gestor de equipe', 'Atleta (Mercado)', 'Plataforma', 'Autenticação e conta', 'Outras'];
    }

    private function skip(LaravelRoute $route, string $uri): bool
    {
        $trimmed = ltrim($uri, '/');
        $name = (string) $route->getName();

        foreach (self::SKIP_PREFIXES as $prefix) {
            if ($trimmed === $prefix || str_starts_with($trimmed, $prefix.'/') || str_starts_with($name, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    /** `/overlay/{channel:token}/{overlay?}` → `/overlay/{channel}/{overlay}` */
    private function path(string $uri): string
    {
        return (string) preg_replace('/\{(\w+)(?::\w+)?\??\}/', '{$1}', $uri);
    }

    /**
     * @param  array<string, mixed>|null  $doc
     * @return Operation
     */
    private function operation(LaravelRoute $route, string $method, string $uri, ?array $doc): array
    {
        $name = $route->getName();
        $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
        $access = $this->access($name, $uri, $middleware);

        $operation = [
            'operationId' => $name ?? strtolower($method).'_'.trim((string) preg_replace('/[^a-z0-9]+/i', '_', $uri), '_'),
            'summary' => $doc['summary'] ?? ($name ?? $uri),
            'tags' => [$this->tag($name, $uri)],
            'parameters' => $this->parameters($uri, $doc['parameters'] ?? []),
            'responses' => $doc['responses'] ?? ['default' => ['description' => 'Resposta ainda não descrita.']],
            'security' => $access === 'Pública' ? [] : [[$this->scheme($access) => []]],
            'x-name' => $name,
            'x-documented' => $doc !== null,
            'x-access' => $access,
            'x-middleware' => $this->visibleMiddleware($middleware),
            'x-feature' => $this->arg($middleware, 'feature'),
            'x-abilities' => $this->abilities($middleware),
            'x-throttle' => $this->arg($middleware, 'throttle'),
        ];

        if (isset($doc['description'])) {
            $operation['description'] = $doc['description'];
        }

        if (isset($doc['request'])) {
            $operation['requestBody'] = ['required' => true, 'content' => [(string) ($doc['request']['content_type'] ?? 'application/json') => ['schema' => $doc['request']['schema'], 'example' => $doc['request']['example'] ?? null]]];
        }

        return $operation;
    }

    /**
     * @param  array<string, string>  $docs
     * @return list<array<string, mixed>>
     */
    private function parameters(string $uri, array $docs): array
    {
        preg_match_all('/\{(\w+)(?::\w+)?(\?)?\}/', $uri, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => [
            'name' => $match[1],
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
            'description' => $docs[$match[1]] ?? self::PARAMETERS[$match[1]] ?? null,
        ], $matches);
    }

    private function tag(?string $name, string $uri): string
    {
        $name ??= '';
        $trimmed = ltrim($uri, '/');

        return match (true) {
            $name === 'api.v1.overlay-channels.state.update' => 'Overlays (OBS)',
            str_starts_with($name, 'api.v1.') => 'API pública v1',
            in_array($name, ['public.games.state', 'overlay.state'], true), str_starts_with($name, 'push.') => 'Tempo real e avisos',
            str_starts_with($name, 'embed.') => 'Widgets',
            str_starts_with($name, 'overlay.') => 'Overlays (OBS)',
            str_starts_with($name, 'panel.') => 'Painel',
            str_starts_with($name, 'manager.') => 'Gestor de equipe',
            str_starts_with($name, 'athlete.'), str_starts_with($name, 'market.') => 'Atleta (Mercado)',
            str_starts_with($name, 'platform.') => 'Plataforma',
            str_starts_with($name, 'registration.'), str_starts_with($trimmed, 'inscricoes'), str_starts_with($trimmed, 'minha-inscricao') => 'Inscrição online',
            str_starts_with($name, 'portal.'), str_starts_with($name, 'public.'), $trimmed === '' => 'Portal público',
            in_array($name, ['login', 'logout', 'register', 'verification.notice', 'dashboard'], true), str_starts_with($name, 'password.'), str_starts_with($name, 'profile.'), str_starts_with($name, 'verification.'), str_starts_with($name, 'legal.'), str_starts_with($name, 'terms.') => 'Autenticação e conta',
            default => 'Outras',
        };
    }

    /**
     * @param  list<string>  $middleware
     */
    private function access(?string $name, string $uri, array $middleware): string
    {
        return match (true) {
            in_array('platform.admin', $middleware, true) => 'Super Admin',
            $name === 'api.v1.overlay-channels.state.update' => 'Chave Bearer do canal',
            str_contains($uri, '{channel:token}') || str_contains($uri, '{channel}') => 'Token do canal na URL',
            str_contains($uri, 'minha-inscricao/{token}') => 'Link pessoal na URL',
            in_array('manager.member', $middleware, true) => 'Gestor de equipe (sessão)',
            in_array('athlete.member', $middleware, true) => 'Atleta (sessão)',
            in_array('panel.member', $middleware, true) => 'Membro do painel (sessão)',
            (bool) array_filter($middleware, fn (string $item) => $item === 'auth' || str_starts_with($item, 'auth:')) => 'Usuário logado (sessão)',
            default => 'Pública',
        };
    }

    private function scheme(string $access): string
    {
        return match ($access) {
            'Chave Bearer do canal' => 'overlayControlToken',
            'Token do canal na URL' => 'overlayToken',
            'Link pessoal na URL' => 'personalLink',
            default => 'session',
        };
    }

    /**
     * @param  list<string>  $middleware
     * @return list<string>
     */
    private function visibleMiddleware(array $middleware): array
    {
        return array_values(array_filter($middleware, fn (string $item) => ! in_array($item, ['web', 'api'], true)));
    }

    /**
     * @param  list<string>  $middleware
     */
    private function arg(array $middleware, string $name): ?string
    {
        foreach ($middleware as $item) {
            if (str_starts_with($item, $name.':')) {
                return substr($item, strlen($name) + 1);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $middleware
     * @return list<string>
     */
    private function abilities(array $middleware): array
    {
        $abilities = [];

        foreach ($middleware as $item) {
            foreach (['panel.can:', 'can:'] as $prefix) {
                if (str_starts_with($item, $prefix)) {
                    $abilities[] = substr($item, strlen($prefix));
                }
            }
        }

        return $abilities;
    }
}
