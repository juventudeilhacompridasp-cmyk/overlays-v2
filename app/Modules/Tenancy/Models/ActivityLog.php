<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Registro de atividade para auditoria (quem fez o quê, onde e com que resultado). Somente de acréscimo: nunca é atualizado
 * nem apagado individualmente (só a limpeza por idade, `activity:prune`).
 *
 * @property string $actor_name
 * @property string|null $actor_role
 * @property string $area
 * @property string $kind
 * @property string $action
 * @property string $summary
 * @property string|null $subject_type
 * @property string|null $subject_label
 * @property string|null $game_id
 * @property string $outcome
 * @property array<int, string>|null $fields
 * @property array<string, array{0: mixed, 1: mixed}>|null $changes
 * @property Carbon $created_at
 */
#[Unguarded]
class ActivityLog extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'activity_log';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['fields' => 'array', 'changes' => 'array', 'created_at' => 'datetime'];
    }
}
