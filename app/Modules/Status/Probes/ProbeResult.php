<?php

namespace App\Modules\Status\Probes;

/** Resultado de uma sondagem: ok/falha com a latência, ou "pulada" quando o componente não está configurado neste ambiente. */
final readonly class ProbeResult
{
    private function __construct(public bool $ok, public ?int $latencyMs, public ?string $detail, public bool $skipped = false)
    {
    }

    public static function ok(?int $latencyMs = null): self
    {
        return new self(true, $latencyMs, null);
    }

    public static function fail(string $detail, ?int $latencyMs = null): self
    {
        return new self(false, $latencyMs, mb_substr($detail, 0, 160));
    }

    public static function skipped(): self
    {
        return new self(true, null, null, true);
    }
}
