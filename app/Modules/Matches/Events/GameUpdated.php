<?php

namespace App\Modules\Matches\Events;

use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Support\PublicGameSnapshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Placar, situação ou relógio do jogo mudou. Vai para o canal público `games.{id}` com o retrato completo
 * (o cliente só substitui o estado: simples e imune a pacotes fora de ordem). Montado na hora do disparo,
 * porque o job da fila não tem organização atual.
 */
class GameUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, mixed>  $snapshot */
    public function __construct(public string $gameId, public array $snapshot) {}

    public static function for(Game $game): self
    {
        return new self((string) $game->getKey(), PublicGameSnapshot::for($game));
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("games.{$this->gameId}")];
    }

    public function broadcastAs(): string
    {
        return 'game.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['snapshot' => $this->snapshot];
    }
}
