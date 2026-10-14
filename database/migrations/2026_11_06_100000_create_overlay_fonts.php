<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fontes próprias da organização para os overlays (WOFF2, WOFF, TTF ou OTF). O arquivo é servido pelo próprio
        // endereço do overlay (mesma origem do OBS), sem depender de CORS no CDN.
        Schema::create('overlay_fonts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('path');
            $table->string('format', 8);
            $table->unsignedInteger('bytes');
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_fonts');
    }
};
