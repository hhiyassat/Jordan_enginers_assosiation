<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-72: per-project cap for a single-project submission by tier.
 *
 * Manual (p. 129): "سقف مساحة المشروع الواحد المسموح بتصميمه ...
 * استشاري: 10500 فأكثر ... سماح بتجاوز حسب بدل دفع 25% من الفرق
 * × نسبة الاختصاص".
 *
 * The cap is per-discipline (some offices are tiered differently per
 * discipline) so it lives on OfficeCeiling alongside m2_allowed.
 * NULL means "no per-project cap for this org+discipline" — the
 * submit gate treats null as pass-through, and no surcharge is
 * computed. Explicit 0 would be a data error (can't submit anything).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_ceilings', function (Blueprint $t) {
            $t->unsignedInteger('per_project_cap_m2')->nullable()->after('m2_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('office_ceilings', function (Blueprint $t) {
            $t->dropColumn('per_project_cap_m2');
        });
    }
};
