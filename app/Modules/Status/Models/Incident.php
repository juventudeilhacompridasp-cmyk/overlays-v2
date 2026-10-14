<?php

namespace App\Modules\Status\Models;

use App\Modules\Status\Enums\Impact;
use App\Modules\Status\Enums\IncidentState;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Incidente ou manutenção programada (`impact = maintenance`). `auto` = aberto pela sondagem. Tabela GLOBAL.
 *
 * @property string $title
 * @property Impact $impact
 * @property IncidentState $state
 * @property bool $auto
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $resolved_at
 */
#[Unguarded]
class Incident extends Model
{
    use HasUlids;

    protected $table = 'status_incidents';

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'impact' => Impact::class,
            'state' => IncidentState::class,
            'auto' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Component, $this> */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'status_incident_component', 'incident_id', 'component_id');
    }

    /** @return HasMany<IncidentUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class, 'incident_id')->orderBy('created_at')->orderBy('id');
    }

    public function isMaintenance(): bool
    {
        return $this->impact === Impact::Maintenance;
    }

    /** Incidente (não manutenção) ainda aberto. */
    public function scopeOpenIncidents(Builder $query): void
    {
        $query->where('impact', '!=', Impact::Maintenance->value)->whereNotIn('state', [IncidentState::Resolved->value]);
    }

    /** Manutenção não concluída. */
    public function scopePendingMaintenance(Builder $query): void
    {
        $query->where('impact', Impact::Maintenance->value)->where('state', '!=', IncidentState::Completed->value);
    }

    /** Manutenção em curso agora: marcada "em andamento" ou dentro da janela agendada. */
    public function maintenanceActive(?Carbon $now = null): bool
    {
        $now ??= now();

        if (! $this->isMaintenance() || $this->state === IncidentState::Completed) {
            return false;
        }

        if ($this->state === IncidentState::InProgress) {
            return true;
        }

        return $this->starts_at->lte($now) && ($this->ends_at === null || $this->ends_at->gt($now));
    }
}
