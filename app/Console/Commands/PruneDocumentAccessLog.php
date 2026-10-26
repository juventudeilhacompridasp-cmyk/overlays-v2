<?php

namespace App\Console\Commands;

use App\Modules\Tenancy\Models\DocumentAccess;
use Illuminate\Console\Command;

class PruneDocumentAccessLog extends Command
{
    /** Quanto tempo o registro de acessos a documentos é guardado. */
    public const KEEP_DAYS = 730;

    protected $signature = 'documents:prune-access-log';

    protected $description = 'Apaga os registros de acesso a documentos pessoais com mais de 2 anos';

    public function handle(): int
    {
        $deleted = DocumentAccess::query()->withoutGlobalScopes()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        $this->info("{$deleted} registro(s) apagado(s).");

        return self::SUCCESS;
    }
}
