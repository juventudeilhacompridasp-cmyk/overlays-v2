<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedido de reforço (ADR 0003): o gestor pede a inclusão de pessoas num elenco já aprovado, pelo mesmo fluxo
        // (cadastro, documentos, análise) da inscrição. `entry_id` já aponta para a inscrição que recebe os reforços.
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('kind', 20)->default('new')->after('status');
            $table->foreignId('requested_by')->nullable()->after('kind')->constrained('users')->nullOnDelete();
        });

        // Histórico das alterações de elenco feitas pelo gestor (camisa, apelido, desligamento, reforços).
        Schema::create('roster_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_changes');

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn('kind');
        });
    }
};
