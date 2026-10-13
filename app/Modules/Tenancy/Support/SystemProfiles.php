<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Perfis de sistema: um por papel interno (`Role::usesPanel()`), com as permissões do papel.
 * Idempotente: chamar de novo só cria o que falta (não mexe em perfil já ajustado pela organização).
 */
final class SystemProfiles
{
    public static function ensure(string $organizationId): void
    {
        foreach (Role::cases() as $role) {
            if (! $role->usesPanel()) {
                continue;
            }

            $existing = DB::table('access_profiles')
                ->where('organization_id', $organizationId)
                ->where('slug', $role->value)
                ->value('id');

            if ($existing !== null) {
                continue;
            }

            $id = (string) Str::ulid();

            DB::table('access_profiles')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'name' => $role->label(),
                'slug' => $role->value,
                'description' => null,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('access_profile_permissions')->insert(
                array_map(fn ($permission) => ['profile_id' => $id, 'permission' => $permission->value], $role->permissions()),
            );
        }
    }
}
