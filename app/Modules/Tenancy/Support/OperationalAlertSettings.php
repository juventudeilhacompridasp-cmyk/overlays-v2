<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Alertas operacionais (`organizations.settings.operational_alerts`): quantas horas à frente olhar (`hours_ahead`, 1 a 72, padrão 24)
 * e quais verificações valem (`lineup`, `officials`, `venue`, `conflicts`; padrão todas). O envio em si é a chave `operational_alerts`
 * de Comunicações e o interruptor da instalação `platform.operational_alerts.enabled`.
 */
final class OperationalAlertSettings
{
    public const HOURS_MIN = 1;

    public const HOURS_MAX = 72;

    public const HOURS_DEFAULT = 24;

    public const CHECKS = ['lineup', 'officials', 'venue', 'conflicts'];

    /** @return array{hours_ahead: int, checks: array<string, bool>} */
    public static function all(?Organization $organization = null): array
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['operational_alerts'] ?? [];
        $saved = is_array($saved) ? $saved : [];
        $hours = is_numeric($saved['hours_ahead'] ?? null) ? (int) $saved['hours_ahead'] : self::HOURS_DEFAULT;
        $checks = [];

        foreach (self::CHECKS as $check) {
            $checks[$check] = is_array($saved['checks'] ?? null) && is_bool($saved['checks'][$check] ?? null) ? $saved['checks'][$check] : true;
        }

        return ['hours_ahead' => max(self::HOURS_MIN, min(self::HOURS_MAX, $hours)), 'checks' => $checks];
    }

    /** Interruptor da instalação (`OPERATIONAL_ALERTS`, padrão ligado). */
    public static function installationEnabled(): bool
    {
        return (bool) config('platform.operational_alerts.enabled', true);
    }

    /** Teto de jogos olhados por rodada (`OPERATIONAL_ALERTS_MAX_GAMES`, 10 a 1000, padrão 200). */
    public static function maxGames(): int
    {
        return max(10, min(1000, (int) config('platform.operational_alerts.max_games', 200)));
    }
}
