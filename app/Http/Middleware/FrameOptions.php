<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem pode colocar uma página dentro de um iframe: os widgets (`embed.*`) podem ser embutidos em qualquer site;
 * todo o resto (painel, login, portal) só no próprio site, contra clickjacking.
 */
class FrameOptions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (str_starts_with((string) $request->route()?->getName(), 'embed.')) {
            $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
            $response->headers->remove('X-Frame-Options');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $response;
    }
}
