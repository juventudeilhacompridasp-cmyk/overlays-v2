<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Overlays livres (Builder): um canvas com até 60 elementos (texto, imagem, forma) desenhado pela organização e
        // mostrado no OBS por canal (/overlay/{token}/personalizado/{id}). Nunca guarda dados de jogo, só o desenho.
        Schema::create('overlay_custom_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('width')->default(1920);
            $table->unsignedInteger('height')->default(1080);
            $table->json('elements');
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_custom_items');
    }
};
