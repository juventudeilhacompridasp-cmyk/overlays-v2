<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Como a organização opera os jogos (`organizations.settings.operation`). Hoje: se o resultado rápido (placar final sem súmula
 * detalhada) é permitido. O padrão é o comportamento original (permitido).
 */
final class OperationSettings
{
    public static function quickResultAllowed(?Organization $organization = null): bool
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['operation']['quick_result'] ?? null;

        return is_bool($saved) ? $saved : true;
    }
}
