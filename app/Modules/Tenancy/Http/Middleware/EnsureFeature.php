<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `feature:{recurso}` — a organização precisa ter o recurso liberado. Painel: 403 com aviso; público: 404 (a página "não existe"). */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $resource = Feature::from($feature);

        if (! CurrentOrganization::has($resource)) {
            abort($request->is('painel/*', 'gestor/*') ? 403 : 404, "O recurso “{$resource->label()}” não está incluído no pacote da sua organização.");
        }

        return $next($request);
    }
}
