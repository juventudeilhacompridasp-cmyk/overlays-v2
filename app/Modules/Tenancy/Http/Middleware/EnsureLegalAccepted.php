<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Support\LegalDocuments;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Painel: enquanto houver versão publicada dos termos/privacidade que o usuário não aceitou, ele só vê a tela de aceite. */
class EnsureLegalAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && LegalDocuments::pendingFor($user)->isNotEmpty()) {
            return redirect()->route('legal.accept.show');
        }

        return $next($request);
    }
}
