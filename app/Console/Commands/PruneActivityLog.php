<?php

namespace App\Console\Commands;

use App\Modules\Tenancy\Models\ActivityLog;
use App\Modules\Tenancy\Support\ActivitySettings;
use Illuminate\Console\Command;

class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune';

    protected $description = 'Apaga os registros de atividade mais antigos que o prazo de guarda (ACTIVITY_LOG_RETENTION_DAYS)';

    public function handle(): int
    {
        $days = ActivitySettings::retentionDays();
        $deleted = ActivityLog::query()->withoutGlobalScopes()->where('created_at', '<', now()->subDays($days))->delete();

        $this->info("{$deleted} registro(s) com mais de {$days} dias apagado(s).");

        return self::SUCCESS;
    }
}
