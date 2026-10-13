<?php

namespace App\Modules\Teams\Support;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameTactic;
use App\Modules\Matches\Support\ManagerLineupWindow;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;

/**
 * Lembrete por e-mail ao gestor cuja equipe ainda não confirmou a escalação (ADR 0003): um aviso quando faltam até 24 h
 * para o prazo e outro, mais urgente, quando faltam até 2 h (limiares e liga/desliga em {@see CommunicationSettings}).
 * Cada aviso sai uma vez por jogo e gestor.
 */
final class LineupReminders
{
    /** @return int quantos e-mails foram enfileirados */
    public static function run(): int
    {
        $sent = 0;

        foreach (Organization::query()->get() as $organization) {
            if (! $organization->hasFeature(Feature::TeamPortal)) {
                continue;
            }

            $sent += (int) CurrentOrganization::using($organization, fn (): int => self::forOrganization($organization));
        }

        return $sent;
    }

    private static function forOrganization(Organization $organization): int
    {
        if (! CommunicationSettings::enabled('manager_lineup_reminder', $organization)) {
            return 0;
        }

        [$first, $second] = CommunicationSettings::reminderMinutes($organization);
        $sent = 0;

        $games = Game::query()
            ->where('status', GameStatus::Scheduled)
            ->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addMinutes($first + 1440))
            ->with(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition'])
            ->get();

        foreach ($games as $game) {
            $deadline = ManagerLineupWindow::deadline($game);

            if ($deadline === null || $deadline->lte(now())) {
                continue;
            }

            $minutes = (int) now()->diffInMinutes($deadline);
            $kind = $minutes <= $second ? 'lineup_2h' : ($minutes <= $first ? 'lineup_24h' : null);

            if ($kind === null) {
                continue;
            }

            foreach ([[$game->homeEntry, $game->awayEntry], [$game->awayEntry, $game->homeEntry]] as [$mine, $other]) {
                if ($mine === null) {
                    continue;
                }

                $confirmed = GameTactic::query()->where('game_id', $game->getKey())->where('entry_id', $mine->getKey())->whereNotNull('confirmed_at')->exists();

                if ($confirmed) {
                    continue;
                }

                $versus = "{$mine->team->name} x ".($other?->team->name ?? 'a definir');

                $sent += ManagerNotifier::send(
                    $organization,
                    $mine->team,
                    $kind,
                    "{$kind}:{$game->getKey()}:{$mine->getKey()}",
                    "Escalação pendente: {$versus}",
                    $kind === 'lineup_2h' ? 'Falta pouco: a escalação ainda não foi confirmada.' : 'A escalação do próximo jogo ainda não foi confirmada.',
                    ["Jogo: {$versus}", 'Quando: '.$game->scheduled_at?->format('d/m/Y H:i'), 'Prazo para confirmar: '.$deadline->format('d/m/Y H:i')],
                    "/gestor/jogos/{$game->getKey()}",
                );
            }
        }

        return $sent;
    }
}
