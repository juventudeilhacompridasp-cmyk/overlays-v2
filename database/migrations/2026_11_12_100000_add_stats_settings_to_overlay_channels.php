<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estatísticas lançadas à mão para o jogo no ar (finalizações, escanteios, posse...) e o visual da tela.
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->json('stats_settings')->nullable()->after('screen_settings');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->dropColumn('stats_settings');
        });
    }
};
