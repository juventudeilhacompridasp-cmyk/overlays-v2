<?php

namespace App\Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Área da plataforma: só administradores da plataforma. Os demais recebem 404 (a área nem "existe" para eles). */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_platform_admin === true, 404);

        return $next($request);
    }
}
