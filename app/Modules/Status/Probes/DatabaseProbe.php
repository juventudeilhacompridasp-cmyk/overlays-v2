<?php

namespace App\Modules\Status\Probes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Banco de dados e cache respondem (consulta simples e gravação/leitura de uma chave). */
class DatabaseProbe implements Probe
{
    public function key(): string
    {
        return 'database';
    }

    public function name(): string
    {
        return 'Banco de dados e cache';
    }

    public function run(): ProbeResult
    {
        $started = hrtime(true);

        try {
            DB::select('select 1');
            Cache::put('status:probe', 'ok', 60);

            if (Cache::get('status:probe') !== 'ok') {
                return ProbeResult::fail('Cache não devolveu o valor gravado');
            }

            return ProbeResult::ok((int) ((hrtime(true) - $started) / 1_000_000));
        } catch (Throwable $e) {
            return ProbeResult::fail('Falha: '.class_basename($e));
        }
    }
}
