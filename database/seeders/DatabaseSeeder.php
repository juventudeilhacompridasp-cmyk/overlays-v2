<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados de demonstração do ambiente LOCAL. Idempotente (pode rodar várias vezes).
     * Login: admin@plataforma.test / password
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@plataforma.test'],
            ['name' => 'Administrador', 'password' => 'password', 'email_verified_at' => now()],
        );
    }
}
