<?php

namespace App\Modules\Status\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma medição de um componente (a cada minuto). Guardada por `retention_days` e depois apagada.
 *
 * @property bool $ok
 * @property int|null $latency_ms
 */
#[Unguarded]
class Check extends Model
{
    protected $table = 'status_checks';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ok' => 'boolean', 'checked_at' => 'datetime'];
    }
}
