<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recursos sob demanda liberados pela plataforma (desligados por padrão em qualquer pacote).
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('opt_in_features')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('opt_in_features');
        });
    }
};
