<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Súmulas enviadas depois do jogo (ADR 0004). O arquivo fica no disco privado; a leitura (parte 2) preenche `extracted`.
        Schema::create('game_sheet_uploads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('original_name', 150);
            $table->string('mime', 120);
            $table->string('kind', 20); // image | pdf | structured
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->string('status', 20)->default('uploaded');
            $table->json('extracted')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'sha256']);
            $table->index(['organization_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sheet_uploads');
    }
};
