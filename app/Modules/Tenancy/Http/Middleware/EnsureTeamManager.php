<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Painel do gestor de equipe (`/gestor`): só entra quem tem o papel de gestor na organização atual (ADR 0003). */
class EnsureTeamManager
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role() !== Role::TeamManager) {
            return Inertia::render('Panel/NoAccess')->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
