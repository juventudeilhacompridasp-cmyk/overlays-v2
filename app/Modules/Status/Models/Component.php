<?php

namespace App\Modules\Status\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Componente técnico monitorado (painel, tempo real, dados, filas, arquivos). Tabela GLOBAL da plataforma.
 *
 * @property string $key
 * @property string $name
 */
#[Unguarded]
class Component extends Model
{
    use HasUlids;

    protected $table = 'status_components';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['visible' => 'boolean'];
    }

    /** @return HasMany<Check, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(Check::class, 'component_id');
    }

    /** @return BelongsToMany<Incident, $this> */
    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class, 'status_incident_component', 'component_id', 'incident_id');
    }
}
