<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Support\OrganizationResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a organização ativa pelo endereço acessado (domínio próprio, subdomínio ou a organização padrão).
 * Roda ANTES do route model binding (ver bootstrap/app.php).
 */
class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        CurrentOrganization::set(OrganizationResolver::forHost($request->getHost()));

        return $next($request);
    }
}
