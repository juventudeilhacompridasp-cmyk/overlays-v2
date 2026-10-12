<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Escalação de cada equipe em um jogo (titulares, reservas e comissão presentes na súmula).
        Schema::create('game_lineups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('slot');
            $table->unsignedSmallInteger('shirt_number')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'person_id']);
            $table->index(['game_id', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_lineups');
    }
};
