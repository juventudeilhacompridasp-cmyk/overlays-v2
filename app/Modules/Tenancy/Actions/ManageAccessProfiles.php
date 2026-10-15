<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Models\AccessProfile;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\AccessAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Perfis de acesso da organização atual (ADR 0005). Perfis de sistema espelham os papéis e não mudam (duplique para
 * personalizar). Um perfil só muda ou some se alguém ainda gerenciar usuários depois.
 */
final class ManageAccessProfiles
{
    public function __construct(private readonly ManageMembers $members) {}

    /**
     * @param  list<string>  $permissions
     */
    public function create(string $name, ?string $description, array $permissions): AccessProfile
    {
        $this->ensureNameFree($name);

        return DB::transaction(function () use ($name, $description, $permissions): AccessProfile {
            $profile = AccessProfile::query()->create([
                'name' => $name,
                'slug' => $this->slugFor($name),
                'description' => $description,
                'is_system' => false,
            ]);

            $this->sync($profile, $permissions);
            AccessAuditLog::record('profile.created', $name, 'Perfil criado com '.count($profile->permissions()).' permissão(ões).');

            return $profile;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    public function update(AccessProfile $profile, string $name, ?string $description, array $permissions): AccessProfile
    {
        $this->ensureEditable($profile);
        $this->ensureNameFree($name, $profile);

        $keepsUserManagement = in_array(Permission::UsersManage->value, $permissions, true);

        if (! $keepsUserManagement) {
            $this->ensureManagersRemain($profile);
        }

        return DB::transaction(function () use ($profile, $name, $description, $permissions): AccessProfile {
            $before = array_map(fn (Permission $permission) => $permission->value, $profile->permissions());
            $profile->update(['name' => $name, 'description' => $description]);
            $this->sync($profile, $permissions);
            AccessAuditLog::record('profile.updated', $name, AccessAuditLog::diff($before, array_map(fn (Permission $permission) => $permission->value, $profile->permissions())));

            return $profile;
        });
    }

    public function duplicate(AccessProfile $profile): AccessProfile
    {
        $base = $profile->name.' (cópia)';
        $name = $base;

        for ($i = 2; AccessProfile::query()->where('name', $name)->exists(); $i++) {
            $name = "{$base} {$i}";
        }

        return $this->create($name, $profile->description, array_map(fn (Permission $permission) => $permission->value, $profile->permissions()));
    }

    public function delete(AccessProfile $profile): void
    {
        $this->ensureEditable($profile);

        $members = Membership::query()->where('access_profile_id', $profile->getKey())->count();

        if ($members > 0) {
            throw new InvalidArgumentException("Este perfil está em uso por {$members} usuário(s). Troque o acesso deles antes de excluir.");
        }

        $profile->delete();
        AccessAuditLog::record('profile.deleted', $profile->name, 'Perfil excluído.');
    }

    private function ensureEditable(AccessProfile $profile): void
    {
        if ($profile->is_system) {
            throw new InvalidArgumentException('Perfis de sistema não podem ser alterados. Duplique para personalizar.');
        }
    }

    private function ensureNameFree(string $name, ?AccessProfile $except = null): void
    {
        $query = AccessProfile::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)]);

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Já existe um perfil com este nome.');
        }
    }

    /** Se os usuários deste perfil perdessem a gestão de usuários, alguém de fora dele precisa continuar com ela. */
    private function ensureManagersRemain(AccessProfile $profile): void
    {
        $holdsNow = Membership::query()->where('access_profile_id', $profile->getKey())->with(['profile.grants', 'overrides'])->get()
            ->contains(fn (Membership $membership) => $this->members->holdsUserManagement($membership));

        if (! $holdsNow) {
            return;
        }

        $others = Membership::query()->with(['profile.grants', 'overrides'])
            ->where(fn ($query) => $query->whereNull('access_profile_id')->orWhere('access_profile_id', '!=', $profile->getKey()))
            ->get()
            ->contains(fn (Membership $membership) => $this->members->holdsUserManagement($membership));

        if (! $others) {
            throw new InvalidArgumentException('A organização precisa ter pelo menos um administrador: este perfil é o único com a gestão de usuários.');
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function sync(AccessProfile $profile, array $permissions): void
    {
        $valid = array_values(array_unique(array_filter($permissions, fn (string $value) => Permission::tryFrom($value) !== null)));

        $profile->grants()->delete();
        $profile->grants()->createMany(array_map(fn (string $value) => ['permission' => $value], $valid));
        $profile->unsetRelation('grants');
    }

    private function slugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'perfil';
        $slug = $base;

        for ($i = 2; AccessProfile::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
