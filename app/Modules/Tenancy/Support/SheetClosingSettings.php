<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;

/**
 * Fechamento da súmula (`organizations.settings.sheet_closing`): quantas assinaturas confirmadas da súmula o fechamento exige
 * (`min_signatures`, 0 a 10, padrão 0 = nenhuma) e se reabrir um jogo com súmula fechada exige um motivo (`reopen_reason`, padrão sim).
 */
final class SheetClosingSettings
{
    public const MIN_SIGNATURES = 0;

    public const MAX_SIGNATURES = 10;

    /** @return array{min_signatures: int, reopen_reason: bool} */
    public static function all(?Organization $organization = null): array
    {
        $organization ??= CurrentOrganization::get();
        $saved = $organization?->settings['sheet_closing'] ?? [];
        $min = is_array($saved) && is_numeric($saved['min_signatures'] ?? null) ? (int) $saved['min_signatures'] : self::MIN_SIGNATURES;

        return [
            'min_signatures' => max(self::MIN_SIGNATURES, min(self::MAX_SIGNATURES, $min)),
            'reopen_reason' => is_array($saved) && is_bool($saved['reopen_reason'] ?? null) ? $saved['reopen_reason'] : true,
        ];
    }
}
