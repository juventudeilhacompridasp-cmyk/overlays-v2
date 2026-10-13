<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avisos por e-mail ao gestor (ADR 0003): o gestor pode desligá-los; cada aviso é enviado uma única vez por chave.
        Schema::table('team_managers', function (Blueprint $table) {
            $table->boolean('notify')->default(true)->after('team_id');
        });

        Schema::create('manager_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('dedupe_key', 120);
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_notifications');

        Schema::table('team_managers', function (Blueprint $table) {
            $table->dropColumn('notify');
        });
    }
};
