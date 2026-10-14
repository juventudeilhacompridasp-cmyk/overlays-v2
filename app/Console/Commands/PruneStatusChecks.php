<?php

namespace App\Console\Commands;

use App\Modules\Status\Models\Check;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('status:prune')]
#[Description('Status page: apaga as medições mais antigas que o período de retenção (90 dias)')]
class PruneStatusChecks extends Command
{
    public function handle(): int
    {
        $days = max(7, (int) config('platform.status.retention_days', 90));
        $removed = Check::query()->where('checked_at', '<', now()->subDays($days)->startOfDay())->delete();

        $this->info("{$removed} medições antigas removidas.");

        return self::SUCCESS;
    }
}
