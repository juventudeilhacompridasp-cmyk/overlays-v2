<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;

/** Métricas de uso de uma organização para o painel da plataforma (só contagens; nada de dado pessoal). */
final class OrganizationUsage
{
    /**
     * @return array{users: int, competitions: int, seasons: int, teams: int, people: int, games: int, finished_games: int, registrations: int, sheet_reads_month: int, sheet_reads_total: int, last_activity: ?string}
     */
    public static function of(Organization $organization): array
    {
        $count = fn (string $table, ?callable $where = null): int => (int) DB::table($table)->where('organization_id', $organization->getKey())->when($where, fn ($query) => $where($query))->count();

        $lastGame = DB::table('games')->where('organization_id', $organization->getKey())->max('updated_at');
        $lastEvent = DB::table('game_events')->where('organization_id', $organization->getKey())->max('created_at');
        $last = max((string) $lastGame, (string) $lastEvent);

        return [
            'users' => $count('memberships'),
            'competitions' => $count('competitions'),
            'seasons' => $count('seasons'),
            'teams' => $count('teams'),
            'people' => $count('people'),
            'games' => $count('games'),
            'finished_games' => $count('games', fn ($query) => $query->where('status', 'finished')),
            'registrations' => $count('registrations'),
            // Leituras de súmula por IA (as que custam): no mês e desde sempre. Só contagens.
            'sheet_reads_month' => $count('game_sheet_reads', fn ($query) => $query->where('reader', 'vision')->where('created_at', '>=', now()->startOfMonth())),
            'sheet_reads_total' => $count('game_sheet_reads', fn ($query) => $query->where('reader', 'vision')),
            'last_activity' => $last !== '' ? $last : null,
        ];
    }
}
