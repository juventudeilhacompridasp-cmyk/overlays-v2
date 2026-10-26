<?php

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem pode colocar uma página dentro de um iframe: os widgets (`embed.*`) podem ser embutidos em qualquer site;
 * todo o resto (painel, login, portal) só no próprio site, contra clickjacking.
 */
class FrameOptions
{
    public function handle(Request $request, Closure $next): Response
    {
        // O `nonce` precisa existir antes de a página ser montada (o Vite e o script do tema o usam).
        $mode = ContentSecurityPolicy::mode();
        $nonce = $mode === 'off' ? null : ContentSecurityPolicy::nonce();

        if ($nonce !== null) {
            Vite::useCspNonce($nonce);
        }

        $response = $next($request);
        $policy = $nonce !== null ? ContentSecurityPolicy::policy($nonce) : null;

        if (str_starts_with((string) $request->route()?->getName(), 'embed.')) {
            $ancestors = 'frame-ancestors *';
            $response->headers->remove('X-Frame-Options');
        } else {
            $ancestors = "frame-ancestors 'self'";
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // Em `enforce` a política vai junto com o anti-clickjacking, no mesmo cabeçalho; em `report` só relata.
        $response->headers->set('Content-Security-Policy', $mode === 'enforce' && $policy !== null ? "{$ancestors}; {$policy}" : $ancestors);

        if ($mode === 'report' && $policy !== null) {
            $response->headers->set('Content-Security-Policy-Report-Only', $policy);
        } else {
            $response->headers->remove('Content-Security-Policy-Report-Only');
        }

        return $response;
    }
}
