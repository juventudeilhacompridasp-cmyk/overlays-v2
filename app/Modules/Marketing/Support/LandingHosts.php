<?php

namespace App\Modules\Marketing\Support;

/**
 * Hosts em que a RAIZ (`/`) é a landing page do produto, quando `platform.landing.root` está ligado: os de
 * `PLATFORM_LANDING_HOSTS` (separados por vírgula) ou, sem isso, o domínio-base e o `www` dele. Desligado (padrão), a
 * landing só existe em `/produto` e o portal da organização continua na raiz.
 */
class LandingHosts
{
    /** @return list<string> */
    public static function all(): array
    {
        if (config('platform.landing.root') !== true) {
            return [];
        }

        $configured = (string) config('platform.landing.hosts');
        $hosts = $configured !== '' ? explode(',', $configured) : [];

        if ($hosts === [] && is_string($base = config('platform.base_domain')) && $base !== '') {
            $hosts = [$base, "www.{$base}"];
        }

        return array_values(array_unique(array_filter(array_map(fn (string $host) => strtolower(trim($host)), $hosts))));
    }
}
