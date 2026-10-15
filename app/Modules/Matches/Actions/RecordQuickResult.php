<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Tenancy\Support\OperationSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lança só o placar final (sem súmula detalhada): registra um gol anônimo por gol, marcado como `quick`,
 * e encerra o jogo. Se já havia um resultado rápido, ele é anulado por eventos compensatórios antes de
 * lançar o novo. Jogos com gols detalhados (súmula) não podem ser alterados por aqui.
 */
final class RecordQuickResult
{
    public function __construct(
        private readonly FinishGame $finish = new FinishGame,
        private readonly RecordShootout $shootout = new RecordShootout,
    ) {}

    public function handle(Game $game, int $homeGoals, int $awayGoals, ?int $recordedBy = null, ?int $homePenalties = null, ?int $awayPenalties = null, ?Carbon $at = null): Game
    {
        if (! OperationSettings::quickResultAllowed()) {
            throw new InvalidArgumentException('O resultado rápido está desligado nesta organização (Painel > Parâmetros). Lance os eventos pela súmula.');
        }

        if (! $game->division->ruleSet()->allowQuickResult) {
            throw new InvalidArgumentException('O regulamento desta edição não permite o resultado rápido. Lance os eventos pela súmula.');
        }

        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if ($game->status->isDecided()) {
            throw new InvalidArgumentException('O jogo já está encerrado. Reabra para lançar de novo.');
        }

        if ($game->sport()->profile()->scoreModel === 'sets') {
            throw new InvalidArgumentException('Em esportes de sets, lance os pontos pela súmula (o resultado rápido não vale).');
        }

        if ($homeGoals < 0 || $awayGoals < 0) {
            throw new InvalidArgumentException('O placar não pode ser negativo.');
        }

        $points = $game->sport()->profile()->scoreModel === 'points';

        if ($points && $homeGoals === $awayGoals) {
            throw new InvalidArgumentException('O basquete não termina empatado: lance o placar depois da prorrogação.');
        }

        return DB::transaction(function () use ($game, $homeGoals, $awayGoals, $recordedBy, $homePenalties, $awayPenalties, $points, $at): Game {
            $goalTypes = $points ? GameEventType::scoring() : [GameEventType::Goal, GameEventType::OwnGoal];

            $effective = $game->events()->effective()->whereIn('type', $goalTypes)->get();

            if ($effective->contains(fn (GameEvent $event) => ($event->payload['quick'] ?? false) !== true)) {
                throw new InvalidArgumentException('Este jogo tem gols lançados na súmula; edite pela súmula.');
            }

            foreach ($effective as $previous) {
                $this->event($game, $previous->type, $previous->entry_id, $recordedBy, ['quick' => true], $previous->getKey(), $at);
            }

            foreach ([[$game->home_entry_id, $homeGoals], [$game->away_entry_id, $awayGoals]] as [$entryId, $goals]) {
                if ($points) {
                    // Um único lançamento anônimo carrega o total da equipe.
                    if ($goals > 0) {
                        $this->event($game, GameEventType::FieldGoal, $entryId, $recordedBy, ['quick' => true, 'points' => $goals], null, $at);
                    }

                    continue;
                }

                for ($i = 0; $i < $goals; $i++) {
                    $this->event($game, GameEventType::Goal, $entryId, $recordedBy, ['quick' => true], null, $at);
                }
            }

            // Disputa de pênaltis (só mata-mata); sem pênaltis informados, remove os de um lançamento anterior.
            if ($homePenalties !== null || $awayPenalties !== null || $game->events()->effective()->where('type', GameEventType::PenaltyKick)->exists()) {
                $this->shootout->handle($game, $homePenalties, $awayPenalties, $recordedBy);
            }

            return $this->finish->handle($game, $at);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(Game $game, GameEventType $type, ?string $entryId, ?int $recordedBy, array $payload, ?string $compensates = null, ?Carbon $at = null): void
    {
        GameEvent::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'type' => $type,
            'period' => 1,
            'entry_id' => $entryId,
            'payload' => $payload,
            'compensates_event_id' => $compensates,
            'recorded_by' => $recordedBy,
            'created_at' => $at ?? now(),
        ]);
    }
}
