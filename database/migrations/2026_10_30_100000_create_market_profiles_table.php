<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mercado de jogadores livres (ADR 0008): um perfil por usuário em cada organização. Não cria `people` nem
        // `roster_entries`; sem CPF. Contato cifrado e data de nascimento nunca saem do atleta (só a faixa de idade).
        Schema::create('market_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('sport', 20)->nullable();
            $table->json('positions')->nullable();
            $table->string('dominant_foot', 10)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('level', 20)->nullable();
            $table->text('bio')->nullable();
            $table->json('availability')->nullable();
            $table->string('city')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('display_name')->nullable();
            $table->date('birth_date');
            $table->string('contact_channel', 20)->nullable();
            $table->text('contact_value')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['organization_id', 'status', 'sport']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_profiles');
    }
};
