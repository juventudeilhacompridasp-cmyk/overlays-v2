<?php

use App\Modules\Tenancy\Support\SystemProfiles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('access_profile_permissions', function (Blueprint $table) {
            $table->foreignUlid('profile_id')->constrained('access_profiles')->cascadeOnDelete();
            $table->string('permission');

            $table->primary(['profile_id', 'permission']);
        });

        // Sem perfil escolhido vale o papel (Role); o perfil só passa a valer quando for atribuído.
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignUlid('access_profile_id')->nullable()->constrained('access_profiles')->nullOnDelete();
        });

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            SystemProfiles::ensure($organizationId);
        }
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_profile_id');
        });

        Schema::dropIfExists('access_profile_permissions');
        Schema::dropIfExists('access_profiles');
    }
};
