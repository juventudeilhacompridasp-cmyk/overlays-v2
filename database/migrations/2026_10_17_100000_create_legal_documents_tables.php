<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Termos de uso e política de privacidade de cada organização. Versões publicadas nunca mudam: editar = nova versão.
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['organization_id', 'type', 'version']);
        });

        // Aceite de um usuário do painel a uma versão (prova de quando e de qual texto).
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('legal_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['user_id', 'legal_document_id']);
        });

        // Versão da política de privacidade aceita no pedido de inscrição (nulo = texto padrão, ainda sem versão publicada).
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignUlid('consent_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consent_document_id');
        });

        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_documents');
    }
};
