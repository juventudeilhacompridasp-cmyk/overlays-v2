<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Papel de um usuário na organização atual.
 *
 * @property Role $role
 * @property string|null $access_profile_id
 */
#[Unguarded]
class Membership extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    /**
     * Permissões efetivas: as do perfil de acesso, se houver, senão as do papel. O gestor de equipe nunca recebe
     * permissão do painel, nem por perfil.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        if (! $this->role->usesPanel()) {
            return [];
        }

        return $this->profile?->permissions() ?? $this->role->permissions();
    }

    /** Nome do acesso para mostrar: o do perfil, ou o do papel. */
    public function accessLabel(): string
    {
        return $this->access_profile_id === null ? $this->role->label() : $this->profile->name;
    }

    /**
     * Perfil de acesso escolhido (nulo = vale o papel).
     *
     * @return BelongsTo<AccessProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class, 'access_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
