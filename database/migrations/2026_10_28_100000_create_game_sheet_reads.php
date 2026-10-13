<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha por leitura pedida (a de IA custa dinheiro: o limite por jogo e por mês conta daqui).
        Schema::create('game_sheet_reads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('upload_id')->nullable()->constrained('game_sheet_uploads')->nullOnDelete();
            $table->string('reader', 20);
            $table->timestamps();

            $table->index(['organization_id', 'reader', 'created_at']);
            $table->index(['game_id', 'reader']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sheet_reads');
    }
};
