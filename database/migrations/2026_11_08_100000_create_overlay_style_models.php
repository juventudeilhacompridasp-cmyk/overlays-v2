<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Meus modelos" dos overlays de campeonato: estilo (aparência, animação, cores, acabamento) guardado com um nome
        // e válido para qualquer visão e canal da organização. Nunca guarda campeonato, edição ou jogo.
        Schema::create('overlay_style_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->json('options');
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_style_models');
    }
};
