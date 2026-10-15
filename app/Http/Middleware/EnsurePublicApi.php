<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API pública de consulta (`/api/v1`): a organização pode desligá-la em Painel > Parâmetros (`settings.public_api` = false). */
class EnsurePublicApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = CurrentOrganization::get();
        $settings = $organization instanceof Organization ? $organization->settings : [];

        abort_if(($settings['public_api'] ?? true) === false, 404);

        return $next($request);
    }
}
