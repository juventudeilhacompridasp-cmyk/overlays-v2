<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Participação de usuários na organização atual. Regra central: a organização nunca fica sem alguém que gerencie usuários
 * (`users.manage`), seja pelo papel de administrador ou por um perfil de acesso.
 */
final class ManageMembers
{
    /** Cria o usuário (e-mail já verificado) com o papel informado. */
    public function create(string $name, string $email, string $password, Role $role, ?AccessProfile $profile = null): User
    {
        $max = CurrentOrganization::get()?->limit('max_users');

        if ($max !== null && Membership::query()->where('role', '!=', Role::TeamManager)->count() >= $max) {
            throw new InvalidArgumentException("O pacote da organização permite no máximo {$max} usuários.");
        }

        return DB::transaction(function () use ($name, $email, $password, $role, $profile): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();

            Membership::query()->create(['user_id' => $user->getKey(), 'role' => $profile !== null ? Role::Viewer : $role, 'access_profile_id' => $profile?->getKey()]);

            return $user;
        });
    }

    public function changeRole(Membership $membership, Role $role): Membership
    {
        return $this->assignAccess($membership, $role, null);
    }

    /**
     * Define o acesso do usuário: um papel padrão ou um perfil de acesso (nesse caso o papel guardado é só a base "consulta").
     */
    public function assignAccess(Membership $membership, ?Role $role, ?AccessProfile $profile): Membership
    {
        $role ??= Role::Viewer;

        if ($role === Role::TeamManager || $membership->role === Role::TeamManager) {
            throw new InvalidArgumentException('O gestor de equipe é gerenciado na tela da equipe.');
        }

        $permissions = $profile !== null ? $profile->permissions() : $role->permissions();

        if ($this->holdsUserManagement($membership) && ! in_array(Permission::UsersManage, $permissions, true)) {
            $this->ensureAnotherManager($membership);
        }

        $membership->update(['role' => $profile !== null ? Role::Viewer : $role, 'access_profile_id' => $profile?->getKey()]);

        return $membership;
    }

    public function remove(Membership $membership): void
    {
        if ($this->holdsUserManagement($membership)) {
            $this->ensureAnotherManager($membership);
        }

        $membership->delete();
    }

    /** Dá o papel inicial ao primeiro usuário de uma instalação nova (sem nenhum administrador). */
    public function claimFirstAdmin(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            if (Membership::query()->exists()) {
                return false;
            }

            Membership::query()->create(['user_id' => $user->getKey(), 'role' => Role::Admin]);

            return true;
        });
    }

    public function holdsUserManagement(Membership $membership): bool
    {
        return in_array(Permission::UsersManage, $membership->permissions(), true);
    }

    /**
     * Garante que, sem este membro (ou sem a permissão dele), alguém continua gerenciando usuários.
     */
    public function ensureAnotherManager(Membership $membership): void
    {
        $others = Membership::query()->with('profile.grants')->whereKeyNot($membership->getKey())->get()
            ->contains(fn (Membership $other) => $this->holdsUserManagement($other));

        if (! $others) {
            throw new InvalidArgumentException('A organização precisa ter pelo menos um administrador.');
        }
    }
}
