<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Patrocinadores dos overlays: logos da organização, reaproveitados por todos os canais. Disco público (CDN), WebP.
        Schema::create('overlay_sponsors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('logo_path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'sort']);
        });

        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->json('sponsor_settings')->nullable()->after('lineup_settings');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_channels', function (Blueprint $table) {
            $table->dropColumn('sponsor_settings');
        });

        Schema::dropIfExists('overlay_sponsors');
    }
};
