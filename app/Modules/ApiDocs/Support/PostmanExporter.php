<?php

namespace App\Modules\ApiDocs\Support;

/**
 * Converte a especificação OpenAPI da aplicação numa coleção do Postman (v2.1): uma pasta por grupo, uma requisição por
 * operação, `{{baseUrl}}` no endereço, parâmetros de caminho como variáveis (`:nome`) e o exemplo do catálogo no corpo.
 * Nenhum segredo vai junto: tokens e chaves ficam como variáveis vazias para a pessoa preencher.
 */
final class PostmanExporter
{
    public const SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public static function fromSpec(array $spec): array
    {
        $folders = [];

        foreach (OpenApiBuilder::operations($spec) as $operation) {
            $folders[$operation['tags'][0]][] = self::request($operation);
        }

        $items = [];

        foreach (OpenApiBuilder::tagNames() as $tag) {
            if (isset($folders[$tag])) {
                $items[] = ['name' => $tag, 'item' => $folders[$tag]];
            }
        }

        return [
            'info' => [
                'name' => (string) $spec['info']['title'],
                'description' => (string) $spec['info']['description'],
                'version' => (string) $spec['info']['version'],
                'schema' => self::SCHEMA,
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => rtrim((string) ($spec['servers'][0]['url'] ?? ''), '/')],
                ['key' => 'channelToken', 'value' => '', 'description' => 'Token secreto do canal de overlay (vai na URL).'],
                ['key' => 'controlKey', 'value' => '', 'description' => 'Chave privada de controle do canal (Bearer).'],
            ],
            'item' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private static function request(array $operation): array
    {
        $path = (string) $operation['path'];
        $segments = array_values(array_filter(explode('/', (string) preg_replace('/\{(\w+)\}/', ':$1', $path)), fn (string $part) => $part !== ''));
        $variables = array_map(fn (array $parameter) => ['key' => $parameter['name'], 'value' => '', 'description' => (string) ($parameter['description'] ?? '')], $operation['parameters'] ?? []);
        $headers = [['key' => 'Accept', 'value' => 'application/json']];

        $request = [
            'method' => $operation['method'],
            'header' => $headers,
            'url' => [
                'raw' => '{{baseUrl}}/'.implode('/', $segments),
                'host' => ['{{baseUrl}}'],
                'path' => $segments,
                'variable' => $variables,
            ],
            'description' => self::description($operation),
        ];

        if (($operation['x-access'] ?? '') === 'Chave Bearer do canal') {
            $request['auth'] = ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{controlKey}}', 'type' => 'string']]];
        }

        foreach ($operation['requestBody']['content'] ?? [] as $contentType => $content) {
            $request['header'][] = ['key' => 'Content-Type', 'value' => $contentType];
            $request['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($content['example'] ?? (object) [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        return ['name' => (string) $operation['summary'], 'request' => $request];
    }

    /** @param  array<string, mixed>  $operation */
    private static function description(array $operation): string
    {
        $lines = array_filter([
            $operation['description'] ?? null,
            'Acesso: '.($operation['x-access'] ?? 'não informado'),
            ! empty($operation['x-feature']) ? 'Recurso do pacote: '.$operation['x-feature'] : null,
            ! empty($operation['x-throttle']) ? 'Limite: '.$operation['x-throttle'] : null,
            ($operation['x-documented'] ?? false) ? null : 'Rota ainda sem descrição no catálogo.',
        ]);

        return implode("\n\n", $lines);
    }
}
