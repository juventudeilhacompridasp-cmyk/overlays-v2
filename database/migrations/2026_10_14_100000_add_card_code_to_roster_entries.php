<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roster_entries', function (Blueprint $table) {
            // Código público da carteirinha (vai no QR); gerado sob demanda e renovável.
            $table->string('card_code', 16)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('roster_entries', function (Blueprint $table) {
            $table->dropColumn('card_code');
        });
    }
};
