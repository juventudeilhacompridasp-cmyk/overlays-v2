<?php

namespace App\Models;

use App\Modules\Tenancy\CurrentOrganization;
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
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, Role|null> papel por organização, lido uma vez por requisição */
    private array $roles = [];

    /**
     * Papel do usuário na organização atual (null = sem acesso).
     */
    public function role(): ?Role
    {
        $organizationId = CurrentOrganization::id();

        if ($organizationId === null) {
            return null;
        }

        if (! array_key_exists($organizationId, $this->roles)) {
            $this->roles[$organizationId] = Membership::query()->where('user_id', $this->getKey())->first()?->role;
        }

        return $this->roles[$organizationId];
    }

    /** Descarta o papel guardado (depois de alterar a participação do usuário). */
    public function forgetRole(): void
    {
        $this->roles = [];
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
            'password' => 'hashed',
        ];
    }
}
