<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Desfaz a ÚLTIMA cobrança lançada da disputa (evento compensatório; o histórico fica). */
final class UndoShootoutKick
{
    public function __construct(private readonly ProjectGameScore $projectScore = new ProjectGameScore) {}

    public function handle(Game $game, ?int $recordedBy = null, ?Carbon $at = null): Game
    {
        RecordShootoutKick::ensureOpen($game);

        $game = DB::transaction(function () use ($game, $recordedBy, $at): Game {
            $last = $game->events()->effective()->where('type', GameEventType::PenaltyKick)->orderByDesc('id')->first();

            if ($last === null) {
                throw new InvalidArgumentException('Não há cobrança para desfazer.');
            }

            if (! isset($last->payload['kick'])) {
                throw new InvalidArgumentException('A disputa foi lançada pelo total; salve o total em branco para removê-la.');
            }

            GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => GameEventType::PenaltyKick,
                'period' => 1,
                'entry_id' => $last->entry_id,
                'payload' => $last->payload,
                'compensates_event_id' => $last->getKey(),
                'recorded_by' => $recordedBy,
                'created_at' => $at ?? now(),
            ]);

            return $this->projectScore->handle($game);
        });

        event(GameUpdated::for($game));

        return $game;
    }
}
