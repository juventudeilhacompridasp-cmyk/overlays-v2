<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de atividades para auditoria: toda ação de escrita feita por um usuário logado (`request`) e as mudanças
        // de jogo e lances da súmula com antes/depois (`change`). Somente de acréscimo. Nome e papel de quem agiu ficam em
        // texto para sobreviver à remoção do usuário; nunca há IP, CPF, senha nem valores digitados (só os NOMES dos campos).
        Schema::create('activity_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120);
            $table->string('actor_role', 40)->nullable();
            $table->string('area', 20);
            $table->string('kind', 10);
            $table->string('action', 120);
            $table->string('method', 8)->nullable();
            $table->string('summary', 255);
            $table->string('subject_type', 30)->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->string('subject_label', 200)->nullable();
            $table->foreignUlid('game_id')->nullable();
            $table->string('outcome', 10);
            $table->unsignedSmallInteger('status')->nullable();
            $table->json('fields')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'user_id', 'created_at']);
            $table->index(['organization_id', 'game_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
