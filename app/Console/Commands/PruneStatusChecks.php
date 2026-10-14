<?php

namespace App\Console\Commands;

use App\Modules\Status\Models\Check;
use App\Modules\Status\Models\Subscriber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('status:prune')]
#[Description('Status page: apaga as medições antigas (90 dias) e as inscrições nos avisos que nunca foram confirmadas (7 dias)')]
class PruneStatusChecks extends Command
{
    public function handle(): int
    {
        $days = max(7, (int) config('platform.status.retention_days', 90));
        $removed = Check::query()->where('checked_at', '<', now()->subDays($days)->startOfDay())->delete();

        $expired = Subscriber::query()->whereNull('confirmed_at')->where('created_at', '<', now()->subDays(Subscriber::PENDING_DAYS))->delete();

        $this->info("{$removed} medições antigas e {$expired} inscrições sem confirmação removidas.");

        return self::SUCCESS;
    }
}
