<?php

namespace App\Modules\Registration\Events;

/**
 * Um pedido de reforço foi aprovado pela organização (as pessoas aprovadas já estão no elenco). Contrato com outros
 * módulos (ex.: Mercado), que reagem sem que a inscrição os conheça.
 */
final readonly class SupplementApproved
{
    public function __construct(public string $registrationId) {}
}
