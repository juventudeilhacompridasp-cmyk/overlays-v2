<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cenas dos overlays: visual + camadas + patrocinadores + apresentação da escalação guardados com um nome, para o
        // operador trocar tudo de uma vez (pré-jogo, jogo, intervalo...). Valem para qualquer canal da organização.
        Schema::create('overlay_scenes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('config');
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_scenes');
    }
};
