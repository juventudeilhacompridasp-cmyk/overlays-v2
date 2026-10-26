<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\Support\SheetClosingSettings;
use InvalidArgumentException;

/**
 * Reabre um jogo encerrado (ou W.O.) para corrigir o resultado: ele sai da classificação até ser encerrado de novo.
 * Os eventos não são apagados (continuam como histórico).
 */
final class ReopenGame
{
    public function __construct(private readonly KnockoutProgress $knockout = new KnockoutProgress) {}

    /** Com a súmula fechada, reabrir exige um motivo (se a organização mantém a regra); o fechamento fica no histórico. */
    public function handle(Game $game, ?string $reason = null, ?string $by = null): Game
    {
        if (! $game->status->isDecided()) {
            throw new InvalidArgumentException('Só jogos encerrados ou com W.O. podem ser reabertos.');
        }

        $closure = $game->sheetClosure;
        $reason = $reason === null ? null : trim($reason);

        if ($closure !== null && SheetClosingSettings::all()['reopen_reason'] && ($reason === null || mb_strlen($reason) < 5)) {
            throw new InvalidArgumentException('A súmula deste jogo está fechada. Informe o motivo da reabertura (mínimo de 5 caracteres).');
        }

        $this->knockout->assertCanRetract($game);

        $wasWalkover = $game->status === GameStatus::Walkover;

        $game->forceFill([
            'status' => GameStatus::Scheduled,
            'finished_at' => null,
            'home_penalties' => null,
            'away_penalties' => null,
            ...($wasWalkover ? ['home_score' => 0, 'away_score' => 0] : []),
        ])->save();

        $closure?->forceFill([
            'reopened_at' => now(),
            'reopened_by_name' => $by !== null ? mb_substr($by, 0, 120) : null,
            'reopen_reason' => $reason === null || $reason === '' ? null : mb_substr($reason, 0, 500),
        ])->save();

        $this->knockout->sync($game);

        return $game;
    }
}
