<?php

namespace App\Modules\Status\Support;

/**
 * Links da status page para e-mails e comandos. Fora de uma requisição `route()` cai no APP_URL; como a página vale em
 * qualquer host, os links usam o APP_URL (ou `status.{domínio-base}` quando há domínio-base) sem depender da requisição.
 */
class StatusLinks
{
    public static function page(): string
    {
        $base = config('platform.base_domain');

        if (is_string($base) && $base !== '') {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return "{$scheme}://status.".strtolower($base);
        }

        return self::root().'/status';
    }

    public static function confirm(string $token): string
    {
        return self::root()."/status/confirmar/{$token}";
    }

    public static function unsubscribe(string $token): string
    {
        return self::root()."/status/cancelar/{$token}";
    }

    public static function preferences(string $token): string
    {
        return self::root()."/status/preferencias/{$token}";
    }

    private static function root(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
