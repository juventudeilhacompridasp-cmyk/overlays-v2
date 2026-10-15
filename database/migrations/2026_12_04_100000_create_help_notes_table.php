<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notas próprias da organização na central de ajuda (ADR 0006, parte 2): procedimentos internos escritos pelo
        // administrador, visíveis só para a própria organização (painel ou gestores). Texto simples, nunca HTML.
        Schema::create('help_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 10); // panel | manager
            $table->string('title', 120);
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_notes');
    }
};
