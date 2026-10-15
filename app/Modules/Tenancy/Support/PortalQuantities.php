<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Quantidades do portal público (`organizations.settings.portal_quantities`): itens por bloco da home, jogos por lista nos
 * widgets, artilheiros por divisão e as listas das estatísticas (maiores resultados, forma recente). O padrão é o comportamento original; o valor salvo sobrescreve, sempre dentro dos limites.
 */
final class PortalQuantities
{
    public const HOME = 8;

    public const WIDGETS = 6;

    public const SCORERS = 10;

    public const STATS_TOP = 5;

    public const STATS_FORM = 5;

    /** @return array{home: int, widgets: int, scorers: int, stats_top: int, stats_form: int} */
    public static function all(?Organization $organization = null): array
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['portal_quantities'] ?? [];
        $saved = is_array($saved) ? $saved : [];

        return [
            'home' => self::within($saved['home'] ?? null, 4, 24, self::HOME),
            'widgets' => self::within($saved['widgets'] ?? null, 3, 20, self::WIDGETS),
            'scorers' => self::within($saved['scorers'] ?? null, 3, 30, self::SCORERS),
            'stats_top' => self::within($saved['stats_top'] ?? null, 3, 10, self::STATS_TOP),
            'stats_form' => self::within($saved['stats_form'] ?? null, 3, 10, self::STATS_FORM),
        ];
    }

    private static function within(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }
}
