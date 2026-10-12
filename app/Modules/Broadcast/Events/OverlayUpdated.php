<?php

namespace App\Modules\Broadcast\Events;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayState;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** O estado do canal mudou (jogo atual, camadas, placar, relógio). Vai para `overlay.{token}` com o estado completo. */
class OverlayUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  array<string, mixed>  $state */
    public function __construct(public string $token, public array $state) {}

    public static function for(OverlayChannel $channel): self
    {
        return new self($channel->token, OverlayState::for($channel));
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("overlay.{$this->token}")];
    }

    public function broadcastAs(): string
    {
        return 'overlay.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['state' => $this->state];
    }
}
