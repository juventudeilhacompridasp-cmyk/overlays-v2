<?php

namespace App\Models;

use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, Membership|null> participação por organização, lida uma vez por requisição */
    private array $memberships = [];

    /**
     * Participação do usuário na organização atual (null = sem acesso).
     */
    private function membership(): ?Membership
    {
        $organizationId = CurrentOrganization::id();

        if ($organizationId === null) {
            return null;
        }

        if (! array_key_exists($organizationId, $this->memberships)) {
            $this->memberships[$organizationId] = Membership::query()
                ->with(['profile.grants', 'overrides'])
                ->where('user_id', $this->getKey())
                ->first();
        }

        return $this->memberships[$organizationId];
    }

    /**
     * Papel do usuário na organização atual (null = sem acesso).
     */
    public function role(): ?Role
    {
        return $this->membership()?->role;
    }

    /**
     * Permissões efetivas na organização atual: as do perfil de acesso, se houver, senão as do papel.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return $this->membership()?->permissions() ?? [];
    }

    /** Nome do perfil ou do papel na organização atual. */
    public function accessLabel(): ?string
    {
        return $this->membership()?->accessLabel();
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Descarta a participação guardada (depois de alterar o papel/perfil do usuário). */
    public function forgetRole(): void
    {
        $this->memberships = [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'password' => 'hashed',
            'operator_layouts' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** A pessoa já ativou (e confirmou) a autenticação em dois fatores? */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
