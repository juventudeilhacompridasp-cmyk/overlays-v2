<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modelos de arte da organização (ADR 0009): formato + estilo (JSON saneado por ArtStyle), valem para qualquer tipo de arte.
        Schema::create('art_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('format', 20)->default('square');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->json('style');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('art_templates');
    }
};
