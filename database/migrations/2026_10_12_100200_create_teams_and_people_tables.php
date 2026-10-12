<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('city')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        // Cadastro único de pessoa (atleta, comissão, árbitro). Documento só como hash (LGPD).
        Schema::create('people', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'document_hash']);
            $table->index(['organization_id', 'name']);
        });

        // Inscrição de uma equipe em uma divisão.
        Schema::create('entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('division_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seed')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['division_id', 'team_id']);
        });

        Schema::create('entry_group', function (Blueprint $table) {
            $table->foreignUlid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entry_id')->constrained()->cascadeOnDelete();

            $table->primary(['group_id', 'entry_id']);
        });

        // Elenco: pessoa vinculada a uma inscrição, com função e número da camisa.
        Schema::create('roster_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role');
            $table->unsignedTinyInteger('shirt_number')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['entry_id', 'person_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_entries');
        Schema::dropIfExists('entry_group');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('people');
        Schema::dropIfExists('teams');
    }
};
