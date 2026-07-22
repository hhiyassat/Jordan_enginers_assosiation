<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every project is attributed to a specific engineer registered under
 * the office. Kept nullable so the seeder can backfill after creating
 * demo engineers; new projects must specify engineer_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->foreignId('engineer_id')->nullable()->after('owner_user_id')
              ->constrained('engineers')->nullOnDelete();
            $t->index(['engineer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->dropIndex(['engineer_id']);
            $t->dropForeign(['engineer_id']);
            $t->dropColumn('engineer_id');
        });
    }
};
