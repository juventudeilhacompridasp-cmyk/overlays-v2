<?php

namespace App\Modules\Broadcast\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Faixa de lance (gol, cartão, substituição) para a tela de eventos. Some sozinha no cliente. */
class OverlayLowerThird implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  array{id: string, kind: string, title: string, subtitle: string|null, team: string|null, color: string|null, minute: int|null}  $banner */
    public function __construct(public string $token, public array $banner) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("overlay.{$this->token}")];
    }

    public function broadcastAs(): string
    {
        return 'overlay.lowerthird';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['banner' => $this->banner];
    }
}
