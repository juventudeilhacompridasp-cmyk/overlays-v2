<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_media', function (Blueprint $table): void {
            $table->string('path')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_media', function (Blueprint $table): void {
            $table->dropColumn('path');
        });
    }
};
