<?php

namespace App\Modules\Status\Probes;

use App\Modules\Status\Jobs\StatusHeartbeat;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Filas (Horizon): a cada sondagem entra um job que só marca a hora; falha quando o job mais antigo sem confirmação passa de
 * `queue_max_age` segundos (fila parada ou muito atrasada).
 */
class QueueProbe implements Probe
{
    public function key(): string
    {
        return 'queue';
    }

    public function name(): string
    {
        return 'Filas e avisos';
    }

    public function run(): ProbeResult
    {
        try {
            $sent = Cache::get('status:queue:sent');
            $beat = Cache::get('status:queue:beat');
            $maxAge = (int) config('platform.status.queue_max_age', 180);
            $acknowledged = ! is_int($sent) || (is_int($beat) && $beat >= $sent);
            $late = ! $acknowledged && (time() - (int) $sent) > $maxAge;

            // Só manda outro job quando o anterior foi confirmado: assim se mede a idade do mais antigo pendente.
            if ($acknowledged) {
                Cache::put('status:queue:sent', time(), 3600);
                StatusHeartbeat::dispatch();
            }

            return $late ? ProbeResult::fail('Job de teste sem execução há mais de '.$maxAge.' s') : ProbeResult::ok();
        } catch (Throwable $e) {
            return ProbeResult::fail('Falha: '.class_basename($e));
        }
    }
}
