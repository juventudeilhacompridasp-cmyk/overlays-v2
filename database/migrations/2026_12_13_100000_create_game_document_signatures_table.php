<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_document_signatures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 30);
            $table->unsignedSmallInteger('document_version')->default(1);
            $table->char('document_hash', 64);
            $table->string('signer_name', 120);
            $table->string('signer_role', 40);
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            $table->char('token_hash', 64)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'document_type', 'document_version']);
            $table->index(['organization_id', 'status']);
            $table->unique(['token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_document_signatures');
    }
};
