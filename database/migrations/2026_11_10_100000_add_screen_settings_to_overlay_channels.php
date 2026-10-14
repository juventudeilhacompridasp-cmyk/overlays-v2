<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telas extras do canal (pré-jogo, comparativo, cartela): texto e contagem regressiva da cartela de transmissão.
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->json('screen_settings')->nullable()->after('lower_third_settings');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->dropColumn('screen_settings');
        });
    }
};
