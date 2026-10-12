<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regras da inscrição online da edição: prazo, instruções, mínimo/máximo de atletas, exigência de documentos.
        // A inscrição só abre com a edição na situação "Inscrições abertas".
        Schema::table('seasons', function (Blueprint $table) {
            $table->json('registration')->nullable();
        });

        // Pedido de inscrição de uma equipe, preenchido online pelo responsável (sem login) por um link pessoal.
        Schema::create('registrations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('season_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('division_id')->constrained()->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('status')->default('draft');
            $table->string('team_name');
            $table->string('team_short_name', 10)->nullable();
            $table->string('team_city')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 30)->nullable();
            // LGPD: o responsável declarou ter lido o aviso de privacidade e ter autorização para informar os dados.
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            // Preenchidos na aprovação.
            $table->foreignUlid('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignUlid('entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['season_id', 'status']);
        });

        Schema::create('registration_people', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('registration_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->date('birth_date')->nullable();
            // CPF nunca em claro: só o HMAC (mesma regra do cadastro de pessoas).
            $table->string('document_hash', 64)->nullable();
            $table->unsignedTinyInteger('shirt_number')->nullable();
            // Menores: responsável legal e declaração de autorização.
            $table->string('guardian_name')->nullable();
            $table->timestamp('guardian_consent_at')->nullable();
            $table->string('review_status')->default('pending');
            $table->string('review_note')->nullable();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index('registration_id');
        });

        // Documentos (RG, atestado, autorização do responsável): disco PRIVADO, só por URL assinada no painel.
        Schema::create('registration_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('registration_person_id')->constrained('registration_people')->cascadeOnDelete();
            $table->string('type');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_documents');
        Schema::dropIfExists('registration_people');
        Schema::dropIfExists('registrations');
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('registration');
        });
    }
};
