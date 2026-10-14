<?php

namespace App\Console\Commands;

use App\Modules\Marketing\Support\LandingMetrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('marketing:prune-metrics')]
#[Description('Landing page: apaga os contadores de medição com mais de 400 dias')]
class PruneMarketingMetrics extends Command
{
    public function handle(): int
    {
        $removed = LandingMetrics::prune();

        $this->info("{$removed} contadores antigos removidos.");

        return self::SUCCESS;
    }
}
