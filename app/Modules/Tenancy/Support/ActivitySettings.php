<?php

namespace App\Modules\Tenancy\Support;

/** Parâmetros da instalação para o registro de atividades (`config/platform.php`, variáveis `ACTIVITY_LOG_*`). */
final class ActivitySettings
{
    public static function enabled(): bool
    {
        return (bool) config('platform.activity_log.enabled', true);
    }

    /** Dias de guarda (30 a 3650). */
    public static function retentionDays(): int
    {
        $value = config('platform.activity_log.retention_days', 365);

        return max(30, min(3650, is_numeric($value) ? (int) $value : 365));
    }

    public static function logRefused(): bool
    {
        return (bool) config('platform.activity_log.log_refused', true);
    }
}
