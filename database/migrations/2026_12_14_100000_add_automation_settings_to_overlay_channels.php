<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table): void {
            $table->json('automation_settings')->nullable()->after('screen_settings');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table): void {
            $table->dropColumn('automation_settings');
        });
    }
};
