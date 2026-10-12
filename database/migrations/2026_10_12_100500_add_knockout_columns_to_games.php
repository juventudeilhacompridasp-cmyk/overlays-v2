<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mata-mata: os jogos de um mesmo confronto (ida e volta) compartilham `tie_id`; o vencedor segue
        // para o confronto `next_tie_id`, no lado `next_slot` ('a' = melhor cabeça de chave, 'b' = o outro).
        Schema::table('games', function (Blueprint $table) {
            $table->string('tie_id', 26)->nullable()->index();
            $table->unsignedTinyInteger('leg')->nullable();
            $table->string('next_tie_id', 26)->nullable();
            $table->string('next_slot', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['tie_id']);
            $table->dropColumn(['tie_id', 'leg', 'next_tie_id', 'next_slot']);
        });
    }
};
