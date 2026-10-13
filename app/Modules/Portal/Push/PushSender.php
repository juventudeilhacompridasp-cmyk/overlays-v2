<?php

namespace App\Modules\Portal\Push;

use App\Modules\Portal\Models\PushSubscription;

/** Envia uma notificação a vários navegadores. Contrato para o envio real (VAPID) poder ser trocado nos testes. */
interface PushSender
{
    /**
     * @param  iterable<PushSubscription>  $subscriptions
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     * @return list<string> ids das inscrições que o serviço de push recusou (expiradas) e devem ser apagadas
     */
    public function send(iterable $subscriptions, array $payload): array;
}
