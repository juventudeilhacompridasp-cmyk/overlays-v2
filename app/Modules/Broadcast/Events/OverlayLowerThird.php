<?php

namespace App\Modules\Broadcast\Events;

use App\Modules\Broadcast\Support\LowerThirdBanner;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Faixa de lance (gol, cartão, substituição) para a tela de eventos. Some sozinha no cliente. */
class OverlayLowerThird implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  array<string, mixed>  $banner  Faixa montada por {@see LowerThirdBanner}. */
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
