<?php

namespace App\Console\Commands;

use App\Modules\Status\Actions\RunProbes;
use App\Modules\Status\Support\StatusPublisher;
use App\Modules\Status\Support\StatusSummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('status:probe')]
#[Description('Status page: sonda os componentes da plataforma, abre/resolve incidentes automáticos e publica o retrato (a cada minuto)')]
class ProbeStatus extends Command
{
    public function handle(RunProbes $probes): int
    {
        $results = $probes->handle();

        foreach ($results as $key => $ok) {
            $this->line(($ok ? '  ok    ' : '  FALHA ').$key);
        }

        StatusPublisher::publish(StatusSummary::build());

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
    }
}
