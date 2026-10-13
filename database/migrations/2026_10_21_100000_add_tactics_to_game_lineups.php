<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esquema tático (ADR 0003): posição de cada integrante na formação, ordem na lista e capitão.
        Schema::table('game_lineups', function (Blueprint $table) {
            $table->string('position', 20)->nullable()->after('slot');
            $table->unsignedSmallInteger('sort')->default(0)->after('position');
            $table->boolean('is_captain')->default(false)->after('sort');
        });

        // Formação escolhida, instruções privadas e confirmação de cada equipe por jogo.
        Schema::create('game_tactics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->string('formation', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_tactics');

        Schema::table('game_lineups', function (Blueprint $table) {
            $table->dropColumn(['position', 'sort', 'is_captain']);
        });
    }
};
