<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Medição ANÔNIMA da landing page (docs/14). Só contadores por dia: nada de IP, user agent, cookie de rastreio,
        // identificador de visitante nem URL. A chave composta (dia, métrica, dimensão) permite incrementar em uma instrução.
        Schema::create('marketing_metrics', function (Blueprint $table) {
            $table->date('day');
            $table->string('metric', 20);
            $table->string('dimension', 30)->default('');
            $table->unsignedBigInteger('total')->default(0);

            $table->primary(['day', 'metric', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_metrics');
    }
};
