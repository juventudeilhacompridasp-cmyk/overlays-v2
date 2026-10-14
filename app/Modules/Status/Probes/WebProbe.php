<?php

namespace App\Modules\Status\Probes;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Painel e portal público: a rota de saúde (`/up`) responde. */
class WebProbe implements Probe
{
    public function key(): string
    {
        return 'web';
    }

    public function name(): string
    {
        return 'Painel e portal público';
    }

    public function run(): ProbeResult
    {
        $started = hrtime(true);

        try {
            $response = Http::timeout(5)->get(rtrim((string) config('app.url'), '/').'/up');
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);

            return $response->successful() ? ProbeResult::ok($ms) : ProbeResult::fail('HTTP '.$response->status(), $ms);
        } catch (Throwable $e) {
            return ProbeResult::fail('Sem resposta: '.class_basename($e));
        }
    }
}
