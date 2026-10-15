<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Janelas do app do mesário (`organizations.settings.mesario`): quantos dias à frente aparecem jogos, por quantas horas ficam os
 * encerrados e o limite de cartões. O padrão é o comportamento original; o valor salvo sobrescreve, sempre dentro dos limites.
 */
final class MesarioSettings
{
    public const DAYS_AHEAD = 2;

    public const FINISHED_HOURS = 6;

    public const LIMIT = 40;

    /** @return array{days_ahead: int, finished_hours: int, limit: int} */
    public static function all(?Organization $organization = null): array
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['mesario'] ?? [];
        $saved = is_array($saved) ? $saved : [];

        return [
            'days_ahead' => self::within($saved['days_ahead'] ?? null, 0, 7, self::DAYS_AHEAD),
            'finished_hours' => self::within($saved['finished_hours'] ?? null, 1, 48, self::FINISHED_HOURS),
            'limit' => self::within($saved['limit'] ?? null, 10, 100, self::LIMIT),
        ];
    }

    private static function within(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }
}
