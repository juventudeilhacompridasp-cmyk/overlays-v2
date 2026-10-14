<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lower thirds (faixas de lance): estilo visual, animação, duração e quais lances aparecem, por canal.
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->json('lower_third_settings')->nullable()->after('sponsor_settings');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->dropColumn('lower_third_settings');
        });
    }
};
