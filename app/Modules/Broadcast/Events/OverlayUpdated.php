<?php

namespace App\Modules\Broadcast\Events;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayState;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * O estado do canal mudou (jogo atual, camadas, placar, relógio). Vai para `overlay.{token}` com o estado completo, SEM passar
 * pela fila: comandos de exibir/ocultar precisam chegar ao OBS na hora, fiéis ao clique do operador.
 */
class OverlayUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param  array<string, mixed>  $state */
    public function __construct(public string $token, public array $state) {}

    public static function for(OverlayChannel $channel): self
    {
        return new self($channel->token, OverlayState::for($channel));
    }

    /**
     * Dispara sem derrubar quem mudou o estado: se o Reverb estiver fora, a mudança já foi gravada e as telas do OBS a pegam
     * pela consulta periódica de contingência.
     */
    public static function dispatchFor(OverlayChannel $channel): void
    {
        try {
            event(self::for($channel));
        } catch (Throwable $exception) {
            report($exception);
        }
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
