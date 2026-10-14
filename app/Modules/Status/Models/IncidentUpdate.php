<?php

namespace App\Modules\Status\Models;

use App\Modules\Status\Enums\IncidentState;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Comunicado da linha do tempo de um incidente (texto público).
 *
 * @property IncidentState $state
 * @property string $body
 * @property Carbon $created_at
 */
#[Unguarded]
class IncidentUpdate extends Model
{
    use HasUlids;

    protected $table = 'status_incident_updates';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['state' => IncidentState::class, 'created_at' => 'datetime'];
    }
}
