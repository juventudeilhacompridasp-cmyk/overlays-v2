<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documentos de uma pessoa com validade (atestado médico, seguro...). Dado pessoal: o arquivo fica no disco privado e só
        // abre por URL assinada. Serve ao aviso antes de vencer e, se a edição exigir, ao bloqueio de escalação.
        Schema::create('person_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('expires_on');
            $table->string('path');
            $table->string('original_name', 150);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->index(['organization_id', 'expires_on']);
            $table->index(['person_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_documents');
    }
};
