<?php

namespace App\Console\Commands;

use App\Modules\Matches\Support\OperationalAlertNotifier;
use Illuminate\Console\Command;

class AlertOperationalProblems extends Command
{
    protected $signature = 'alerts:operational';

    protected $description = 'Avisa por e-mail quem gerencia competições sobre jogos próximos sem escalação, árbitro, estádio ou com conflito de agenda';

    public function handle(): int
    {
        $sent = OperationalAlertNotifier::run();

        $this->info("{$sent} e-mail(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
