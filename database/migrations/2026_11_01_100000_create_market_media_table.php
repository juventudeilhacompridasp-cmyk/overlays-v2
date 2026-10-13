<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vídeos de jogadas do perfil do Mercado (ADR 0008, parte 4): SÓ link externo, nunca arquivo. A foto continua em
        // `market_profiles.photo_path`.
        Schema::create('market_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('profile_id')->constrained('market_profiles')->cascadeOnDelete();
            $table->string('kind', 20)->default('video_link');
            $table->string('provider', 20);
            $table->string('external_id', 80);
            $table->string('url', 300);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['profile_id', 'provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_media');
    }
};
