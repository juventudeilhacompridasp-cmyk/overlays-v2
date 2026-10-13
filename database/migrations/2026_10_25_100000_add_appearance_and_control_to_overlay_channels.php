<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->json('appearance')->nullable()->after('layers');
            $table->string('control_token_hash', 64)->nullable()->unique()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->dropColumn(['appearance', 'control_token_hash']);
        });
    }
};
