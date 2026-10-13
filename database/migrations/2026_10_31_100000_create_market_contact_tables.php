<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedidos de contato do gestor ao atleta (ADR 0008, parte 3). Um pendente por perfil × equipe (conferido na ação);
        // o contato do atleta só é lido por quem tem um pedido ACEITO.
        Schema::create('market_contact_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('profile_id')->constrained('market_profiles')->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('message', 300);
            $table->string('status', 20)->default('pending');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['profile_id', 'team_id', 'status']);
            $table->index(['team_id', 'created_at']);
        });

        // O atleta bloqueia uma equipe: ela não vê mais o perfil nem pode pedir contato.
        Schema::create('market_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('profile_id')->constrained('market_profiles')->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['profile_id', 'team_id']);
        });

        Schema::table('market_profiles', function (Blueprint $table) {
            // Aviso de "seu perfil vai expirar" já enviado neste ciclo (zera ao publicar/renovar).
            $table->timestamp('expiry_notice_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('market_profiles', fn (Blueprint $table) => $table->dropColumn('expiry_notice_at'));
        Schema::dropIfExists('market_blocks');
        Schema::dropIfExists('market_contact_requests');
    }
};
