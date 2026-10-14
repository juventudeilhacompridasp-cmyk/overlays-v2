<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inscritos nos avisos de incidente da status page (docs/14, parte 3). Tabela GLOBAL: só o e-mail e um token secreto
        // (confirmar e cancelar). Dupla confirmação: sem `confirmed_at` nada é enviado e a inscrição expira em 7 dias.
        Schema::create('status_subscribers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email', 160)->unique();
            $table->string('token', 40)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_subscribers');
    }
};
