<?php

namespace App\Console\Commands;

use App\Modules\Teams\Support\DocumentReminders;
use Illuminate\Console\Command;

class RemindDocumentExpiry extends Command
{
    protected $signature = 'documents:remind';

    protected $description = 'Avisa por e-mail os gestores de equipe sobre documentos de atletas vencidos ou perto de vencer';

    public function handle(): int
    {
        $sent = DocumentReminders::run();

        $this->info("{$sent} aviso(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
