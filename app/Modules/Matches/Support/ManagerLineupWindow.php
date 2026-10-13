<?php

namespace App\Modules\Matches\Support;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use Illuminate\Support\Carbon;

/**
 * Janela em que o gestor da equipe pode definir a escalação (ADR 0003): jogo ainda não iniciado e antes do prazo do
 * regulamento (`lineup.manager_deadline_minutes` antes do horário do jogo; jogo sem horário = até começar).
 * Depois disso o mesário e o organizador seguem podendo ajustar pela súmula, até o primeiro lançamento.
 */
final class ManagerLineupWindow
{
    public static function deadline(Game $game): ?Carbon
    {
        if ($game->scheduled_at === null) {
            return null;
        }

        return $game->scheduled_at->copy()->subMinutes($game->division->ruleSet()->managerLineupDeadlineMinutes);
    }

    /** Motivo pelo qual o gestor não pode mais editar (nulo = pode editar). */
    public static function closedReason(Game $game): ?string
    {
        if ($game->status !== GameStatus::Scheduled) {
            return $game->status->isDecided() ? 'O jogo já foi encerrado.' : 'O jogo já começou ou não está agendado; a escalação não pode mais ser alterada por aqui.';
        }

        $deadline = self::deadline($game);

        if ($deadline !== null && now()->greaterThanOrEqualTo($deadline)) {
            return 'O prazo para definir a escalação terminou em '.$deadline->format('d/m/Y H:i').'. Fale com o organizador se precisar de ajuste.';
        }

        return null;
    }
}
