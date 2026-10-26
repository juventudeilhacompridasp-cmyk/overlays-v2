<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title', 120)->nullable();
            $table->string('provider', 20);
            $table->string('external_id', 120);
            $table->string('url', 500);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->index(['game_id', 'published', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_media');
    }
};
