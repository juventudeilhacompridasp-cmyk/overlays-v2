<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveDefaultOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => config('platform.default_organization_slug')],
            ['name' => 'Organização padrão'],
        );

        CurrentOrganization::set($organization);

        return $next($request);
    }
}
