<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de uma mudança de acesso (perfil, papel, exceção, remoção). Somente de acréscimo.
 *
 * @property string $action
 * @property string|null $subject
 * @property string $summary
 */
#[Unguarded]
class AccessAudit extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'access_audit';

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
