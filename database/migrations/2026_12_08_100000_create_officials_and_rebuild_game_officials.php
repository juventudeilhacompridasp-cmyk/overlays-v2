<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Árbitros, assistentes e mesários: cadastro enxuto (nome e contato do aviso da escala). Não são `people`: sem CPF,
        // foto ou nascimento, e não aparecem na lista de atletas e comissão.
        Schema::create('officials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 160)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'name']);
        });

        // A tabela antiga ligava a escala a `people` e nunca foi usada por nenhuma tela: refeita ligando ao cadastro de árbitros.
        Schema::dropIfExists('game_officials');
        Schema::create('game_officials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('official_id')->constrained('officials')->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'official_id']);
            $table->unique(['game_id', 'role']);
            $table->index('official_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_officials');
        Schema::dropIfExists('officials');

        Schema::create('game_officials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['game_id', 'person_id', 'role']);
        });
    }
};
