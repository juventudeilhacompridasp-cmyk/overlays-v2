<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Rules\Sheet\SheetEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lança um evento na súmula (gol, gol contra, cartão, substituição), validando-o contra a escalação e o
 * regulamento, e atualiza o placar. O primeiro lançamento coloca o jogo "em andamento".
 */
final class RecordGameEvent
{
    public function __construct(
        private readonly BuildGameSheet $sheet = new BuildGameSheet,
        private readonly ProjectGameScore $projectScore = new ProjectGameScore,
    ) {}

    private const SHEET_TYPES = [
        GameEventType::Goal, GameEventType::OwnGoal, GameEventType::YellowCard, GameEventType::RedCard,
        GameEventType::BlueCard, GameEventType::Substitution,
    ];

    public function handle(Game $game, GameEventType $type, string $entryId, ?string $personId, ?string $secondaryPersonId = null, int $period = 1, ?int $minute = null, ?int $recordedBy = null): GameEvent
    {
        if (! in_array($type, self::SHEET_TYPES, true)) {
            throw new InvalidArgumentException('Este tipo de lançamento não é suportado na súmula.');
        }

        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if ($entryId !== $game->home_entry_id && $entryId !== $game->away_entry_id) {
            throw new InvalidArgumentException('A equipe não participa deste jogo.');
        }

        if ($game->status->isDecided() || in_array($game->status, [GameStatus::Cancelled, GameStatus::Postponed], true)) {
            throw new InvalidArgumentException('O jogo não está aberto para lançamentos.');
        }

        return DB::transaction(function () use ($game, $type, $entryId, $personId, $secondaryPersonId, $period, $minute, $recordedBy): GameEvent {
            $error = $this->sheet->handle($game)->check(new SheetEvent($type, $entryId, $personId, $secondaryPersonId, $period, $minute));

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            $event = GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => $type,
                'period' => $period,
                'minute' => $minute,
                'entry_id' => $entryId,
                'person_id' => $personId,
                'secondary_person_id' => $type === GameEventType::Substitution ? $secondaryPersonId : null,
                'recorded_by' => $recordedBy,
            ]);

            if ($game->status === GameStatus::Scheduled) {
                $game->forceFill(['status' => GameStatus::InProgress, 'started_at' => $game->started_at ?? now()]);
            }

            $this->projectScore->handle($game);

            return $event;
        });
    }
}
