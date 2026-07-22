<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-71: governorate on the quota consumption row.
 *
 * The JEA 2025 manual (p. 127) grants +10% overflow for a discipline
 * ONLY in the governorate where the office has already consumed
 * ≥90% of its ceiling:
 *
 *   "يتم منح ما نسبته 10% من الحصص الهندسية للإختصاص في المحافظة
 *    التي تصل نسبة الإستهلاك فيها 90%"
 *
 * QuotaLedger needs to count consumption per (office, discipline,
 * year, governorate) to check that 90% trigger — hence the column.
 *
 * Nullable so historical consumption rows (created before this
 * migration) don't need backfill. Rows without governorate simply
 * don't count toward any governorate's 90% trigger — conservative
 * (they still count against the office's overall ceiling via the
 * existing per-discipline sum, so nothing over-approves).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_consumptions', function (Blueprint $t) {
            // 32 chars matches Disciplines constants + long-form
            // governorate keys ('amman', 'irbid', ...).
            $t->string('governorate', 32)->nullable()->after('discipline');
            $t->index(['organization_id', 'discipline', 'year', 'governorate'],
                      'qc_org_disc_year_gov_idx');
        });
    }

    public function down(): void
    {
        Schema::table('quota_consumptions', function (Blueprint $t) {
            $t->dropIndex('qc_org_disc_year_gov_idx');
            $t->dropColumn('governorate');
        });
    }
};
