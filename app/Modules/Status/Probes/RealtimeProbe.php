<?php

namespace App\Modules\Status\Probes;

/** Servidor de tempo real (Reverb) aceita conexões: usa o endereço interno do broadcaster. Placar e overlays dependem dele. */
class RealtimeProbe implements Probe
{
    public function key(): string
    {
        return 'realtime';
    }

    public function name(): string
    {
        return 'Placar e overlays ao vivo';
    }

    public function run(): ProbeResult
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ProbeResult::skipped();
        }

        $host = (string) config('broadcasting.connections.reverb.options.host');
        $port = (int) config('broadcasting.connections.reverb.options.port', 8080);

        if ($host === '') {
            return ProbeResult::skipped();
        }

        $started = hrtime(true);
        $socket = @fsockopen($host, $port, $code, $message, 3);

        if ($socket === false) {
            return ProbeResult::fail('Conexão recusada ou sem resposta');
        }

        fclose($socket);

        return ProbeResult::ok((int) ((hrtime(true) - $started) / 1_000_000));
    }
}
