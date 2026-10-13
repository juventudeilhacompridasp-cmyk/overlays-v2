<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Organization;

/**
 * Endereço de um caminho no site da organização, calculado fora de uma requisição (comando agendado, fila), onde
 * `route()` usaria o APP_URL e não o endereço da organização (domínio próprio ou subdomínio). Dentro de requisições
 * continue usando `route()`.
 */
final class OrganizationUrl
{
    public static function to(Organization $organization, string $path): string
    {
        $app = (string) config('app.url');
        $path = '/'.ltrim($path, '/');
        $host = OrganizationResolver::publicHost($organization);

        if ($host === null) {
            return rtrim($app, '/').$path;
        }

        $scheme = parse_url($app, PHP_URL_SCHEME);

        return (is_string($scheme) ? $scheme : 'https')."://{$host}{$path}";
    }
}
