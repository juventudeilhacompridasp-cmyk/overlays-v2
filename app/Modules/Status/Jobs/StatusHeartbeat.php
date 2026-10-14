<?php

namespace App\Modules\Status\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Job de teste da status page: só confirma que a fila está processando. */
class StatusHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put('status:queue:beat', time(), 3600);
    }
}
