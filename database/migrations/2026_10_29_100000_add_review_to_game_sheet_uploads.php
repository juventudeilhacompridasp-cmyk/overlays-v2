<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leitura corrigida por uma pessoa (vale para o relatório de conferência) e o registro do que ela mudou.
        Schema::table('game_sheet_uploads', function (Blueprint $table) {
            $table->json('reviewed')->nullable();
            $table->json('review_changes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_sheet_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed', 'review_changes', 'reviewed_at']);
        });
    }
};
