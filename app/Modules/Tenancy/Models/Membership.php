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
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Permissões efetivas: as do perfil de acesso (ou do papel) + exceções `allow` − exceções `deny` (negar vence). O gestor
     * de equipe nunca recebe permissão do painel, nem por perfil nem por exceção.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        if (! $this->role->usesPanel()) {
            return [];
        }

        $permissions = $this->basePermissions();

        foreach ($this->overrides as $override) {
            $permission = Permission::tryFrom($override->permission);

            if ($permission !== null && $override->effect === 'allow' && ! in_array($permission, $permissions, true)) {
                $permissions[] = $permission;
            }
        }

        $denied = $this->overrides->where('effect', 'deny')->pluck('permission')->all();

        return array_values(array_filter($permissions, fn (Permission $permission) => ! in_array($permission->value, $denied, true)));
    }

    /**
     * O que vem do perfil ou do papel, sem exceções.
     *
     * @return list<Permission>
     */
    public function basePermissions(): array
    {
        return $this->role->usesPanel() ? ($this->profile?->permissions() ?? $this->role->permissions()) : [];
    }

    /**
     * @return HasMany<MembershipPermission, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(MembershipPermission::class, 'membership_id');
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
