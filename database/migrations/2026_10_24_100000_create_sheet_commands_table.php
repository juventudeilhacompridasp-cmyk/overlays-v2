<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila offline do mesário: cada lançamento enviado em lote tem uma chave gerada no aparelho. Guardar as chaves já
        // aplicadas impede que um reenvio (queda de conexão no meio, duas abas) lance o mesmo gol duas vezes.
        Schema::create('sheet_commands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained()->cascadeOnDelete();
            $table->string('client_key', 64);
            $table->string('kind', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['game_id', 'client_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_commands');
    }
};
