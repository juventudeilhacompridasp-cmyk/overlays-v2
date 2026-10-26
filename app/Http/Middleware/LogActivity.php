<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\Support\ActivityRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro de atividades (auditoria): depois que a resposta sai, grava a ação de escrita de quem está logado. Roda no
 * `terminate`, então não atrasa o mesário; falha de gravação nunca derruba a requisição (ver `ActivityRecorder`).
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        ActivityRecorder::request($request, $response);
    }
}
