<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Enums\Ability;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * `panel.can:{ability}` — exige a permissão para tudo que escreve. Consultar (GET) só pede ser membro,
 * exceto os formulários de criação/edição (`*.create`, `*.edit`), que são parte da escrita, e a área de usuários.
 */
class EnsurePanelAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $name = (string) $request->route()?->getName();
        // Usuários e papéis são restritos até para consulta; nas demais áreas ver é liberado a qualquer membro.
        $restricted = $ability === Ability::ManageUsers->value;
        $readOnly = ! $restricted && $request->isMethodSafe() && ! str_ends_with($name, '.create') && ! str_ends_with($name, '.edit');

        if (! $readOnly && Gate::denies($ability)) {
            throw new AuthorizationException('Você não tem permissão para esta ação.');
        }

        return $next($request);
    }
}
