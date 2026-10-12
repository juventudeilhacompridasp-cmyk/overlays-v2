<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Papel do usuário em cada organização (hoje só existe a organização padrão).
        Schema::create('memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        // Quem já usava o painel (todos administravam tudo) não pode ficar sem acesso: o usuário mais antigo
        // vira administrador e os demais organizadores. Instalações novas não têm usuários e seguem sem nada aqui.
        $users = DB::table('users')->orderBy('id')->pluck('id');

        if ($users->isEmpty()) {
            return;
        }

        $slug = config('platform.default_organization_slug');
        $organization = DB::table('organizations')->where('slug', $slug)->value('id');

        if ($organization === null) {
            $organization = (string) Str::ulid();
            DB::table('organizations')->insert(['id' => $organization, 'name' => 'Organização padrão', 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach ($users as $index => $userId) {
            DB::table('memberships')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization,
                'user_id' => $userId,
                'role' => $index === 0 ? 'admin' : 'organizer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
