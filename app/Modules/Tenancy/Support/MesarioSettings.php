<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Janelas do app do mesário (`organizations.settings.mesario`): quantos dias à frente aparecem jogos, por quantas horas ficam os
 * encerrados, o limite de cartões e quantas alterações recentes o painel do operador mostra. O padrão é o comportamento original; o valor salvo sobrescreve, sempre dentro dos limites.
 */
final class MesarioSettings
{
    public const DAYS_AHEAD = 2;

    public const FINISHED_HOURS = 6;

    public const LIMIT = 40;

    /** Quantas alterações recentes o painel do operador mostra por jogo (0 esconde o histórico). */
    public const HISTORY = 15;

    /** @return array{days_ahead: int, finished_hours: int, limit: int, history: int} */
    public static function all(?Organization $organization = null): array
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['mesario'] ?? [];
        $saved = is_array($saved) ? $saved : [];

        return [
            'days_ahead' => self::within($saved['days_ahead'] ?? null, 0, 7, self::DAYS_AHEAD),
            'finished_hours' => self::within($saved['finished_hours'] ?? null, 1, 48, self::FINISHED_HOURS),
            'limit' => self::within($saved['limit'] ?? null, 10, 100, self::LIMIT),
            'history' => self::within($saved['history'] ?? null, 0, 50, self::HISTORY),
        ];
    }

    private static function within(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }
}
