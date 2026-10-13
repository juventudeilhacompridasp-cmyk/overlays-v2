<?php

namespace App\Modules\Broadcast\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de teste do tempo real (`php artisan realtime:check`): sai na hora pelo Reverb, no canal público
 * `realtime.check`, sem passar pela fila. Não carrega dado nenhum além de um código aleatório.
 */
class RealtimePing implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $token) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('realtime.check')];
    }

    public function broadcastAs(): string
    {
        return 'realtime.ping';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['token' => $this->token];
    }
}
