<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em toda resposta (web e API). Não substitui um CSP completo (o clickjacking já é tratado em
 * `FrameOptions`); só aplica o que não depende do conteúdo da página e não quebra OBS, widgets nem players de vídeo.
 *
 * - `X-Content-Type-Options: nosniff`: o navegador não "adivinha" o tipo de um arquivo enviado;
 * - `Referrer-Policy`: a URL completa (com tokens de link pessoal) não vaza para outros sites;
 * - `Permissions-Policy`: câmera, microfone, localização etc. desligados (nenhuma tela usa);
 * - `Strict-Transport-Security`: só em requisição HTTPS e com `platform.security.hsts_seconds` > 0.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');

        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if (! $headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        }

        $hsts = (int) config('platform.security.hsts_seconds', 0);

        if ($hsts > 0 && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', "max-age={$hsts}");
        }

        return $response;
    }
}
