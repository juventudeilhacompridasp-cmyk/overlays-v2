<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avisos por componente (docs/14): lista das chaves de componente que a pessoa acompanha. Nulo = todos (o padrão de
        // quem já estava inscrito). Continua sem dado pessoal além do e-mail.
        Schema::table('status_subscribers', function (Blueprint $table) {
            $table->json('components')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('status_subscribers', function (Blueprint $table) {
            $table->dropColumn('components');
        });
    }
};
