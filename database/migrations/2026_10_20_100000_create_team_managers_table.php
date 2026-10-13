<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gestor de equipe (ADR 0003): vínculo usuário × equipe dentro da organização. O papel `manager` em `memberships`
        // só abre o painel do gestor; este vínculo é o que define QUAIS equipes ele enxerga.
        Schema::create('team_managers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
            $table->index(['organization_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_managers');
    }
};
