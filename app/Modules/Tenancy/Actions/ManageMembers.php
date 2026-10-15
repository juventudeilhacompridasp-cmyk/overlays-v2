<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\AccessAuditLog;
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

        if ($max !== null && Membership::query()->whereNotIn('role', Role::outsiders())->count() >= $max) {
            throw new InvalidArgumentException("O pacote da organização permite no máximo {$max} usuários.");
        }

        return DB::transaction(function () use ($name, $email, $password, $role, $profile): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();

            Membership::query()->create(['user_id' => $user->getKey(), 'role' => $profile !== null ? Role::Viewer : $role, 'access_profile_id' => $profile?->getKey()]);
            AccessAuditLog::record('member.created', $name, 'Acesso criado: '.($profile instanceof AccessProfile ? $profile->name : $role->label()).'.');

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

        if (! $role->usesPanel() || ! $membership->role->usesPanel()) {
            throw new InvalidArgumentException('Gestor de equipe e atleta não são gerenciados aqui.');
        }

        $permissions = $profile !== null ? $profile->permissions() : $role->permissions();

        if ($this->holdsUserManagement($membership) && ! in_array(Permission::UsersManage, $permissions, true)) {
            $this->ensureAnotherManager($membership);
        }

        $from = $membership->accessLabel();
        $membership->update(['role' => $profile !== null ? Role::Viewer : $role, 'access_profile_id' => $profile?->getKey()]);
        $membership->unsetRelation('profile');
        AccessAuditLog::record('member.access', $membership->user->name, "Acesso alterado de {$from} para {$membership->accessLabel()}.");

        return $membership;
    }

    /**
     * Exceções do usuário sobre o perfil/papel: `allow` soma e `deny` retira (negar vence). Substitui as anteriores; chaves
     * iguais ao que o perfil já dá (allow) ou já não dá (deny) não têm efeito e são descartadas.
     *
     * @param  list<string>  $allow
     * @param  list<string>  $deny
     */
    public function setOverrides(Membership $membership, array $allow, array $deny): Membership
    {
        if (! $membership->role->usesPanel()) {
            throw new InvalidArgumentException('Gestor de equipe e atleta não são gerenciados aqui.');
        }

        $valid = fn (array $values): array => array_values(array_unique(array_filter($values, fn (string $value) => Permission::tryFrom($value) !== null)));
        $deny = $valid($deny);
        $allow = array_values(array_diff($valid($allow), $deny));
        $base = array_map(fn (Permission $permission) => $permission->value, $membership->basePermissions());
        $allow = array_values(array_diff($allow, $base));
        $deny = array_values(array_intersect($deny, $base));

        $effective = array_values(array_diff(array_merge($base, $allow), $deny));

        if ($this->holdsUserManagement($membership) && ! in_array(Permission::UsersManage->value, $effective, true)) {
            $this->ensureAnotherManager($membership);
        }

        $before = array_map(fn (Permission $permission) => $permission->value, $membership->permissions());

        DB::transaction(function () use ($membership, $allow, $deny): void {
            $membership->overrides()->delete();
            $membership->overrides()->createMany([
                ...array_map(fn (string $value) => ['permission' => $value, 'effect' => 'allow'], $allow),
                ...array_map(fn (string $value) => ['permission' => $value, 'effect' => 'deny'], $deny),
            ]);
        });

        $membership->unsetRelation('overrides');
        AccessAuditLog::record('member.overrides', $membership->user->name, AccessAuditLog::diff($before, array_map(fn (Permission $permission) => $permission->value, $membership->permissions())));

        return $membership;
    }

    public function remove(Membership $membership): void
    {
        if ($this->holdsUserManagement($membership)) {
            $this->ensureAnotherManager($membership);
        }

        AccessAuditLog::record('member.removed', $membership->user->name, 'Acesso removido ('.$membership->accessLabel().').');
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
        $others = Membership::query()->with(['profile.grants', 'overrides'])->whereKeyNot($membership->getKey())->get()
            ->contains(fn (Membership $other) => $this->holdsUserManagement($other));

        if (! $others) {
            throw new InvalidArgumentException('A organização precisa ter pelo menos um administrador.');
        }
    }
}
