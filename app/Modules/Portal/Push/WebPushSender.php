<?php

namespace App\Modules\Portal\Push;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Envio real por Web Push (VAPID), em lote. */
final class WebPushSender implements PushSender
{
    public function send(iterable $subscriptions, array $payload): array
    {
        if (! PushConfig::enabled()) {
            return [];
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('webpush.subject'),
            'publicKey' => (string) config('webpush.public_key'),
            'privateKey' => (string) config('webpush.private_key'),
        ]]);

        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription->getKey();
            $webPush->queueNotification(
                Subscription::create(['endpoint' => $subscription->endpoint, 'keys' => ['p256dh' => $subscription->p256dh, 'auth' => $subscription->auth]]),
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            );
        }

        $expired = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired() && isset($byEndpoint[$report->getEndpoint()])) {
                $expired[] = (string) $byEndpoint[$report->getEndpoint()];
            }
        }

        return $expired;
    }
}
