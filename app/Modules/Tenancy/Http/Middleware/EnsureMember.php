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
        $role = $request->user()?->role();

        // O gestor de equipe tem o próprio painel (/gestor); o painel do organizador nem para consulta.
        if ($role === null || ! $role->usesPanel()) {
            return Inertia::render('Panel/NoAccess')->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
