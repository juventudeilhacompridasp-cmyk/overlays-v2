<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sheet_closures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closed_by_name', 120);
            $table->timestamp('closed_at');
            $table->unsignedSmallInteger('signatures_count')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->string('reopened_by_name', 120)->nullable();
            $table->string('reopen_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['game_id', 'reopened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sheet_closures');
    }
};
