<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_custom_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('path');
            $table->unsignedInteger('variant_size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_custom_assets');
    }
};
