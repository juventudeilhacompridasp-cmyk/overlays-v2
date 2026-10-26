<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Política opcional da instalação (`platform.two_factor.required_for_admins`): quem administra usuários da organização ou é
 * Super Admin só usa o painel com a autenticação em dois fatores ativa. Sem ela, vai para Meu perfil ativar.
 */
class EnsureTwoFactorForAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('platform.two_factor.required_for_admins') || $user === null || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($user->is_platform_admin || $user->hasPermission(Permission::UsersManage)) {
            return redirect()->route('profile.edit')->with('error', 'Para administrar, ative primeiro a autenticação em dois fatores.');
        }

        return $next($request);
    }
}
