<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perguntas feitas à ajuda com IA (ADR 0006, parte 3). Serve para os limites (por pessoa/dia e por organização/mês)
        // e para a documentação melhorar: o TEXTO da pergunta só é guardado quando ficou sem resposta (e já sem e-mails e
        // números longos); pergunta respondida guarda apenas a contagem.
        Schema::create('help_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('charged')->default(false); // chegou ao provedor (conta nos limites)
            $table->boolean('answered')->default(false);
            $table->string('question', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_questions');
    }
};
