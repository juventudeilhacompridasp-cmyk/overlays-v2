<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exceções por usuário (ADR 0005, parte 3): permissões somadas (allow) ou retiradas (deny) do perfil/papel.
        Schema::create('membership_permissions', function (Blueprint $table) {
            $table->foreignUlid('membership_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);
            $table->string('effect', 5); // allow | deny

            $table->primary(['membership_id', 'permission']);
        });

        // Trilha de auditoria de acesso: quem mudou o quê. Sem IP; o nome da pessoa fica no texto para sobreviver à remoção.
        Schema::create('access_audit', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject', 160)->nullable();
            $table->text('summary');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit');
        Schema::dropIfExists('membership_permissions');
    }
};
