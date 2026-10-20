<?php

namespace App\Support;

/**
 * Quais proxies o Laravel aceita como "o caminho até o usuário" para ler X-Forwarded-*.
 *
 * Confiar em TODOS (`*`) deixa qualquer um forjar o próprio IP mandando um `X-Forwarded-For` falso: o limite de tentativas de
 * login (chave e-mail + IP) e os limites por IP das rotas públicas passariam a valer para um IP inventado a cada requisição.
 * Por isso o padrão confia só nos endereços PRIVADOS (rede interna do Docker, onde ficam o nginx, o Nginx Proxy Manager e o
 * cloudflared). O IP real do visitante, que o Cloudflare/NPM acrescenta ao final da cadeia, é público e portanto não confiável,
 * e é ele que o Laravel usa. `TRUSTED_PROXIES=*` continua possível, mas só se o app NUNCA for alcançável por fora do proxy.
 */
final class TrustedProxies
{
    public const PRIVATE_RANGES = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1', '::1', 'fc00::/7'];

    /** @return list<string>|string */
    public static function resolve(?string $configured): array|string
    {
        $configured = trim((string) $configured);

        if ($configured === '') {
            return self::PRIVATE_RANGES;
        }

        if ($configured === '*' || $configured === '**') {
            return $configured;
        }

        $list = array_values(array_filter(array_map('trim', explode(',', $configured)), fn (string $item) => $item !== ''));

        return $list === [] ? self::PRIVATE_RANGES : $list;
    }
}
