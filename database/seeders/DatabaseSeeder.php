<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados de demonstração do ambiente LOCAL. Idempotente (pode rodar várias vezes).
     * Login: admin@plataforma.test / password
     */
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => config('platform.default_organization_slug')],
            ['name' => 'Organização padrão'],
        );

        $admin = User::query()->firstOrNew(['email' => 'admin@plataforma.test']);

        $admin->forceFill([
            'name' => 'Administrador',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        Membership::query()->withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'user_id' => $admin->getKey()],
            ['role' => Role::Admin],
        );
    }
}
