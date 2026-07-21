<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-79: office classification tier per JEA manual p.96-97.
 *
 * Every applicant User (an engineering office) belongs to one of
 * four registration tiers, which drives the registration + annual
 * dues amounts:
 *   • individual_engineer  (مصنف مهندس أو رأي) — reg 60, annual 30
 *   • engineering          (مصنف هندسي)          — reg 80, annual 60
 *   • consultant           (استشاري)              — reg 100, annual 80
 *   • foreign              (غير أردني)            — reg 3500, annual 2000
 *
 * Nullable — for backfilled applicant users we default to
 * 'individual_engineer' (the cheapest tier) via a follow-up UPDATE
 * so a fresh install without an admin classification decision
 * doesn't accidentally bill a real office at consultant rates.
 * Non-applicant users leave the column null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('office_classification', 32)->nullable()->after('has_iso_cert');
            $t->index('office_classification');
        });

        // Backfill: existing applicant users default to individual_engineer.
        \DB::table('users')
            ->where('role', 'applicant')
            ->whereNull('office_classification')
            ->update(['office_classification' => 'individual_engineer']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['office_classification']);
            $t->dropColumn('office_classification');
        });
    }
};
