<?php

namespace App\Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Só entra no painel quem tem papel na organização atual; os demais veem a tela "sem acesso". */
class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role() === null) {
            return Inertia::render('Panel/NoAccess')->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
