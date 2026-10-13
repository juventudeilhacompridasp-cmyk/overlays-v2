<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Participação de usuários na organização atual. Regra central: a organização nunca fica sem administrador.
 */
final class ManageMembers
{
    /** Cria o usuário (e-mail já verificado) com o papel informado. */
    public function create(string $name, string $email, string $password, Role $role): User
    {
        $max = CurrentOrganization::get()?->limit('max_users');

        if ($max !== null && Membership::query()->count() >= $max) {
            throw new InvalidArgumentException("O pacote da organização permite no máximo {$max} usuários.");
        }

        return DB::transaction(function () use ($name, $email, $password, $role): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();

            Membership::query()->create(['user_id' => $user->getKey(), 'role' => $role]);

            return $user;
        });
    }

    public function changeRole(Membership $membership, Role $role): Membership
    {
        if ($membership->role === Role::Admin && $role !== Role::Admin) {
            $this->ensureAnotherAdmin($membership);
        }

        $membership->update(['role' => $role]);

        return $membership;
    }

    public function remove(Membership $membership): void
    {
        if ($membership->role === Role::Admin) {
            $this->ensureAnotherAdmin($membership);
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

    private function ensureAnotherAdmin(Membership $membership): void
    {
        $others = Membership::query()->where('role', Role::Admin)->whereKeyNot($membership->getKey())->exists();

        if (! $others) {
            throw new InvalidArgumentException('A organização precisa ter pelo menos um administrador.');
        }
    }
}
