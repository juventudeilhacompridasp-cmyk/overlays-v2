<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Denúncias do Mercado (ADR 0008, parte 5): o gestor denuncia um perfil; o atleta denuncia uma equipe.
        Schema::create('market_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('profile_id')->constrained('market_profiles')->cascadeOnDelete();
            $table->foreignUlid('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reporter_kind', 10);
            $table->string('reason', 500);
            $table->string('status', 20)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::table('market_profiles', function (Blueprint $table) {
            $table->string('suspended_reason', 300)->nullable();
            $table->timestamp('suspended_at')->nullable();
        });

        // Pedido de reforço aberto a partir de um contato aceito (sem chave estrangeira: o módulo Registration não conhece o Mercado).
        Schema::table('market_contact_requests', function (Blueprint $table) {
            $table->ulid('registration_id')->nullable();
        });

        // A permissão nova (`market.moderate`) vale para os perfis de sistema de administrador e organizador que já existem.
        $profiles = DB::table('access_profiles')->where('is_system', true)->whereIn('slug', ['admin', 'organizer'])->pluck('id');

        foreach ($profiles as $id) {
            DB::table('access_profile_permissions')->updateOrInsert(['profile_id' => $id, 'permission' => 'market.moderate']);
        }
    }

    public function down(): void
    {
        DB::table('access_profile_permissions')->where('permission', 'market.moderate')->delete();

        Schema::table('market_contact_requests', fn (Blueprint $table) => $table->dropColumn('registration_id'));
        Schema::table('market_profiles', fn (Blueprint $table) => $table->dropColumn(['suspended_reason', 'suspended_at']));
        Schema::dropIfExists('market_reports');
    }
};
