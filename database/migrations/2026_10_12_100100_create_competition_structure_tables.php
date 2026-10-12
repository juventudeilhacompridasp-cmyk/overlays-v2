<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sport')->default('football');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('draft');
            // Regulamento parametrizável (pontuação, desempates, cartões, substituições...).
            $table->json('rules')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'name']);
        });

        // "Especificação": categoria x sexo dentro de uma edição (ex.: SUB-15 masculino).
        Schema::create('divisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('gender')->default('mixed');
            // Sobrescreve as regras da edição para esta divisão, quando necessário.
            $table->json('rules')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'name']);
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('division_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('sequence');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['division_id', 'sequence']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('stage_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();

            $table->unique(['stage_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('competitions');
    }
};
