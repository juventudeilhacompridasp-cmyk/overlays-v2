<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('plan')->default('basic');
            // Recursos liberados à mão; nulo = segue o pacote. Limites: {"max_users": 5, "max_competitions": 2}; nulo = sem limite.
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
        });

        // Quem já usa a plataforma não perde nada: organizações existentes ficam no pacote completo.
        DB::table('organizations')->update(['plan' => 'pro']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['plan', 'features', 'limits']);
        });
    }
};
