<?php

namespace App\Modules\Status\Probes;

/** Uma verificação de saúde de um componente. Nunca lança: falha vira `ProbeResult::fail`. */
interface Probe
{
    /** Chave do componente (`status_components.key`). */
    public function key(): string;

    public function name(): string;

    public function run(): ProbeResult;
}
