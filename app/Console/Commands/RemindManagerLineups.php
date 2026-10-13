<?php

namespace App\Console\Commands;

use App\Modules\Teams\Support\LineupReminders;
use Illuminate\Console\Command;

class RemindManagerLineups extends Command
{
    protected $signature = 'managers:remind-lineups';

    protected $description = 'Avisa por e-mail os gestores de equipe que ainda não confirmaram a escalação de um jogo próximo do prazo';

    public function handle(): int
    {
        $sent = LineupReminders::run();

        $this->info("{$sent} lembrete(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
