<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->string('club_key', 120)->nullable()->after('team_id');
            $table->string('sponsor_key', 120)->nullable()->after('club_key');
            $table->index(['division_id', 'club_key']);
            $table->index(['division_id', 'sponsor_key']);
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->dropIndex('entries_division_id_club_key_index');
            $table->dropIndex('entries_division_id_sponsor_key_index');
            $table->dropColumn(['club_key', 'sponsor_key']);
        });
    }
};
