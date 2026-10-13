<?php

namespace App\Modules\Market\Http\Middleware;

use App\Modules\Tenancy\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Painel do atleta (`/atleta`): só entra quem tem o papel de atleta na organização atual (ADR 0008). */
class EnsureAthlete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role() !== Role::Athlete) {
            return Inertia::render('Panel/NoAccess')->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
