<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Tenancy\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perfil de acesso da organização: um conjunto de permissões que pode ser dado a vários usuários.
 * Perfis de sistema (`is_system`) espelham os papéis originais e não podem ser apagados.
 *
 * @property string $name
 * @property string $slug
 * @property bool $is_system
 */
#[Unguarded]
class AccessProfile extends Model
{
    use BelongsToOrganization, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * @return HasMany<AccessProfilePermission, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(AccessProfilePermission::class, 'profile_id');
    }

    /**
     * Permissões do perfil que ainda existem no catálogo.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return $this->grants->map(fn (AccessProfilePermission $grant) => Permission::tryFrom($grant->permission))
            ->filter()
            ->values()
            ->all();
    }
}
