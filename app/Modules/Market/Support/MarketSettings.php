<?php

namespace App\Modules\Market\Support;

use App\Modules\Tenancy\CurrentOrganization;

/** Parâmetros do Mercado por organização (`organizations.settings.market`); o padrão vem de `config('platform.market')`. */
final class MarketSettings
{
    /** Pedidos de contato que cada equipe pode enviar por dia. */
    public static function dailyRequests(): int
    {
        $saved = CurrentOrganization::get()?->settings['market']['daily_requests'] ?? null;

        return is_numeric($saved) && (int) $saved >= 1 ? min((int) $saved, 100) : (int) config('platform.market.daily_requests', 10);
    }

    /** Dias que um pedido espera resposta antes de expirar. */
    public static function requestDays(): int
    {
        return (int) config('platform.market.request_days', 14);
    }
}
