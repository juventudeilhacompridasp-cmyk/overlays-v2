<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contatos vindos da landing page do produto (docs/14). Tabela GLOBAL da plataforma: sem organization_id, sem CPF,
        // sem IP e sem telefone. O aceite da política de privacidade fica registrado em `consent_at`.
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 160);
            $table->string('organization_name', 120)->nullable();
            $table->string('interest', 40)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->timestamp('consent_at');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
