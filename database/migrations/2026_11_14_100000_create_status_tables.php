<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status page da plataforma (docs/14): tabelas GLOBAIS (sem organization_id). Nunca guardam dado de organização,
        // atleta ou token: só componentes técnicos, medições e comunicados.
        Schema::create('status_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        Schema::create('status_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->boolean('ok');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('detail', 160)->nullable();
            $table->timestamp('checked_at')->index();

            $table->index(['component_id', 'checked_at']);
        });

        Schema::create('status_incidents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 120);
            $table->string('impact', 20);
            $table->string('state', 20);
            $table->boolean('auto')->default(false);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'starts_at']);
        });

        Schema::create('status_incident_component', function (Blueprint $table) {
            $table->foreignUlid('incident_id')->constrained('status_incidents')->cascadeOnDelete();
            $table->foreignUlid('component_id')->constrained('status_components')->cascadeOnDelete();
            $table->primary(['incident_id', 'component_id']);
        });

        Schema::create('status_incident_updates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('incident_id')->constrained('status_incidents')->cascadeOnDelete();
            $table->string('state', 20);
            $table->text('body');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_incident_updates');
        Schema::dropIfExists('status_incident_component');
        Schema::dropIfExists('status_incidents');
        Schema::dropIfExists('status_checks');
        Schema::dropIfExists('status_components');
    }
};
