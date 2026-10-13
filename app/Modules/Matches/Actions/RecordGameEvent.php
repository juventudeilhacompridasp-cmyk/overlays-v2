<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Events\GameEventRecorded;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Rules\Scoring\SetMatch;
use App\Modules\Rules\Sheet\SheetEvent;
use Illuminate\Support\Carbon;
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
        GameEventType::BlueCard, GameEventType::Substitution, GameEventType::Foul, GameEventType::Point,
        GameEventType::FreeThrow, GameEventType::FieldGoal, GameEventType::ThreePointer,
    ];

    /** @param  Carbon|null  $at  quando o lance aconteceu (lançamento vindo da fila offline); padrão: agora */
    public function handle(Game $game, GameEventType $type, string $entryId, ?string $personId, ?string $secondaryPersonId = null, int $period = 1, ?int $minute = null, ?int $recordedBy = null, ?Carbon $at = null): GameEvent
    {
        if (! in_array($type, self::SHEET_TYPES, true)) {
            throw new InvalidArgumentException('Este tipo de lançamento não é suportado na súmula.');
        }

        if ($type === GameEventType::Foul && ! $game->sport()->profile()->tracksTeamFouls()) {
            throw new InvalidArgumentException('Este esporte não registra faltas na súmula.');
        }

        if (in_array($type, [GameEventType::Goal, GameEventType::OwnGoal], true) && $game->sport()->profile()->scoreModel !== 'goals') {
            throw new InvalidArgumentException('Este esporte não usa gols.');
        }

        if ($type->pointValue() !== null && $game->sport()->profile()->scoreModel !== 'points') {
            throw new InvalidArgumentException('Este esporte não tem lances de 1, 2 ou 3 pontos.');
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

        if ($type === GameEventType::Point) {
            return $this->recordPoint($game, $entryId, $recordedBy, $at);
        }

        $event = DB::transaction(function () use ($game, $type, $entryId, $personId, $secondaryPersonId, $period, $minute, $recordedBy, $at): GameEvent {
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
                'created_at' => $at ?? now(),
            ]);

            if ($game->status === GameStatus::Scheduled) {
                $game->forceFill(['status' => GameStatus::InProgress, 'started_at' => $game->started_at ?? ($at ?? now())]);
            }

            $this->projectScore->handle($game);

            return $event;
        });

        // Falta não muda o placar: transmite o contador explicitamente.
        if ($type === GameEventType::Foul) {
            event(GameUpdated::for($game));
        }

        GameEventRecorded::dispatch((string) $game->getKey(), (string) $event->getKey());

        return $event;
    }

    /**
     * Ponto de rally (esportes de sets): o set é o da vez (calculado a partir dos pontos anteriores), nunca informado.
     */
    private function recordPoint(Game $game, string $entryId, ?int $recordedBy, ?Carbon $at = null): GameEvent
    {
        if ($game->sport()->profile()->scoreModel !== 'sets') {
            throw new InvalidArgumentException('Este esporte não usa pontos de rally.');
        }

        return DB::transaction(function () use ($game, $entryId, $recordedBy, $at): GameEvent {
            $match = new SetMatch($game->division->ruleSet());
            $sides = ProjectGameScore::pointSides($game);
            $error = $match->canScore($sides);

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            $event = GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => GameEventType::Point,
                'period' => $match->state($sides)['current'],
                'entry_id' => $entryId,
                'recorded_by' => $recordedBy,
                'created_at' => $at ?? now(),
            ]);

            if ($game->status === GameStatus::Scheduled) {
                $game->forceFill(['status' => GameStatus::InProgress, 'started_at' => $game->started_at ?? ($at ?? now())]);
            }

            $this->projectScore->handle($game);

            return $event;
        });
    }
}
