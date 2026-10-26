<?php

namespace App\Support\Security;

/**
 * Content-Security-Policy da aplicação (docs/17, seção 5). Modo da instalação (`platform.security.csp.mode`):
 * `off` (sem cabeçalho), `report` (padrão: só relata violações em `Content-Security-Policy-Report-Only`, nunca bloqueia) e
 * `enforce` (bloqueia). Scripts só do próprio site ou com o `nonce` da requisição; players de vídeo só dos provedores aceitos
 * no Mercado/Mídia; WebSocket aberto (`ws:`/`wss:`) porque o Reverb e o OBS do operador (`ws://localhost:4455`) variam por instalação.
 * O anti-clickjacking (`frame-ancestors`) continua em `FrameOptions`, que junta tudo num cabeçalho só.
 */
final class ContentSecurityPolicy
{
    public const MODES = ['off', 'report', 'enforce'];

    public static function mode(): string
    {
        $mode = strtolower((string) config('platform.security.csp.mode', 'report'));

        return in_array($mode, self::MODES, true) ? $mode : 'report';
    }

    /** Fontes extras (separadas por espaço) somadas às diretivas de recurso: útil no desenvolvimento (Vite) ou com CDN própria. */
    public static function extra(): string
    {
        $extra = trim((string) config('platform.security.csp.extra_sources', ''));

        // Só tokens de fonte válidos: nada de ponto e vírgula nem aspas soltas que injetem diretivas.
        $tokens = array_filter(preg_split('/\s+/', $extra) ?: [], fn (string $token): bool => preg_match('#^(https?|wss?)://[A-Za-z0-9.\-*:]+(/[A-Za-z0-9._~/\-]*)?$#', $token) === 1);

        return implode(' ', $tokens);
    }

    public static function nonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    public static function policy(string $nonce): string
    {
        $extra = self::extra();
        $plus = fn (string $base): string => trim($base.' '.$extra);
        $realtime = self::realtimeOrigin();

        $directives = [
            "default-src 'self'",
            $plus("script-src 'self' 'nonce-{$nonce}'"),
            $plus("style-src 'self' 'unsafe-inline'"),
            $plus("img-src 'self' data: blob: https:"),
            $plus("font-src 'self' data:"),
            $plus("connect-src 'self' ws: wss:".($realtime !== null ? " {$realtime}" : '')),
            $plus("media-src 'self' blob: data: https:"),
            "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://www.instagram.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
            'report-uri /csp-report',
        ];

        return implode('; ', $directives);
    }

    private static function realtimeOrigin(): ?string
    {
        $host = config('platform.realtime.host');

        if (! is_string($host) || $host === '' || preg_match('/^[A-Za-z0-9.\-]+$/', $host) !== 1) {
            return null;
        }

        $scheme = config('platform.realtime.scheme') === 'https' ? 'https' : 'http';
        $port = (int) config('platform.realtime.port', 8081);

        return "{$scheme}://{$host}:{$port}";
    }
}
