<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quem pediu aviso (anônimo: só o endereço do navegador no serviço de push) de um jogo ou de uma equipe.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 10); // game | team
            $table->ulid('target_id');
            $table->text('endpoint');
            $table->char('endpoint_hash', 64);
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();

            $table->unique(['organization_id', 'target_type', 'target_id', 'endpoint_hash'], 'push_subscriptions_unique');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
