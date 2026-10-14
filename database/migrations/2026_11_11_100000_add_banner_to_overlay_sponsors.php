<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banner largo opcional do patrocinador (barra de patrocinadores 1500 x 200 e formatos "banner" da rotação).
        Schema::table('overlay_sponsors', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_sponsors', function (Blueprint $table) {
            $table->dropColumn('banner_path');
        });
    }
};
