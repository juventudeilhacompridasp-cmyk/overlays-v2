<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Canal de transmissão: uma URL fixa por overlay no OBS, que acompanha o "jogo atual" escolhido no painel.
        // O token na URL é o segredo de acesso (e o nome do canal no Reverb).
        Schema::create('overlay_channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 40)->unique();
            $table->foreignUlid('game_id')->nullable()->constrained('games')->nullOnDelete();
            // Camadas sob demanda (escalação, tabela) que o operador mostra e esconde durante a transmissão.
            $table->json('layers')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_channels');
    }
};
