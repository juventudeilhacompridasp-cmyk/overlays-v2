<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Isso ajudou?" da central de ajuda (ADR 0006, parte 2): um voto por pessoa e artigo (o último vale). Só o
        // identificador do artigo (slug do arquivo) e sim/não; nenhum texto livre, para não guardar dado pessoal.
        Schema::create('help_feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->boolean('helpful');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['slug', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_feedback');
    }
};
