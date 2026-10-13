<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imagens da organização para as artes (ADR 0009, parte 2): fundos, logotipos e selos. Reencodadas em WebP no envio
        // (sem EXIF/GPS) e guardadas no disco público; o navegador as lê por uma rota do painel (mesma origem).
        Schema::create('art_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 80);
            $table->string('path');
            $table->unsignedSmallInteger('variant_size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('art_assets');
    }
};
