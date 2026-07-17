<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JEA services plan defines a phased rollout — each service is tagged
 * with an integer 1..5 indicating which delivery phase it belongs to.
 * Frontend renders a coloured indicator (1 green, 2 orange, 3 red,
 * 4 blue, 5 purple) so operators can see coverage at a glance.
 *
 * NULL means the service pre-dates the phase plan / is unclassified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_definitions', function (Blueprint $t) {
            $t->unsignedTinyInteger('phase')->nullable()->after('status');
            $t->index(['organization_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::table('service_definitions', function (Blueprint $t) {
            $t->dropIndex(['organization_id', 'phase']);
            $t->dropColumn('phase');
        });
    }
};
