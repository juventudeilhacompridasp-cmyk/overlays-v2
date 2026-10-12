<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('games', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('division_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('round')->nullable();
            $table->unsignedInteger('number')->nullable();
            // Nulos permitem jogos de mata-mata ainda sem confronto definido.
            $table->foreignUlid('home_entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->foreignUlid('away_entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->foreignUlid('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->default('scheduled');
            // Projeção dos eventos (fonte da verdade = game_events); W.O. define o placar direto.
            $table->unsignedSmallInteger('home_score')->default(0);
            $table->unsignedSmallInteger('away_score')->default(0);
            $table->unsignedSmallInteger('home_penalties')->nullable();
            $table->unsignedSmallInteger('away_penalties')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stage_id', 'round']);
            $table->index(['division_id', 'scheduled_at']);
        });

        Schema::create('game_officials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['game_id', 'person_id', 'role']);
        });

        // Somente de acréscimo: nunca atualizar nem apagar; desfazer = novo evento que compensa.
        Schema::create('game_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedTinyInteger('period')->default(1);
            $table->unsignedSmallInteger('minute')->nullable();
            $table->foreignUlid('entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->nullOnDelete();
            // Substituição: person_id = quem sai, secondary_person_id = quem entra.
            $table->foreignUlid('secondary_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->foreignUlid('compensates_event_id')->nullable()->constrained('game_events')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
        Schema::dropIfExists('game_officials');
        Schema::dropIfExists('games');
        Schema::dropIfExists('venues');
    }
};
