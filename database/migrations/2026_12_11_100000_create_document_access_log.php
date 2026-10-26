<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quem abriu documentos pessoais (inscrição, atestados, súmulas enviadas). Somente de acréscimo e sem IP; o nome de
        // quem abriu e a descrição do documento ficam no texto para sobreviver à remoção do usuário ou do registro.
        Schema::create('document_access_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120);
            $table->string('kind', 30);
            $table->string('label', 200);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_log');
    }
};
