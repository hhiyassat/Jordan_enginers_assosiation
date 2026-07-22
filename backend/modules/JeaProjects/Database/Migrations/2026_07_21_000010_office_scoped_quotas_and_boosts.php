<?php

use Modules\JeaProjects\Models\Engineer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-77: move quota + ceiling + boost from Organization to per-office User.
 *
 * In the JEA data model an "engineering office" is a User with
 * role='applicant', NOT an Organization. Multiple offices can live
 * under one Organization (the JEA installation). The quota / ceiling
 * / boost data therefore belongs on the office user, not the
 * enclosing Organization — otherwise every office in the same org
 * shares the same ceiling, which is wrong.
 *
 * This migration:
 *   • Adds `office_user_id` (nullable FK to users) to
 *     office_ceilings, quota_consumptions, office_coalition_members.
 *   • Adds three boost flag columns to users (mirror of what was on
 *     organizations pre-JORD-77).
 *   • Backfills every existing row: for each Organization that has
 *     applicant users, tie the ceiling/consumption/coalition row to
 *     that org's primary applicant user (deterministic pick: lowest
 *     user id). Boost flags copy from Organization → each applicant
 *     user in that org.
 *
 * organization_id columns are left in place as dead-but-safe columns
 * — future reads MUST use office_user_id. If we later want to drop
 * them, that's a separate migration after we've verified nothing
 * still references them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Schema — add columns.
        Schema::table('office_ceilings', function (Blueprint $t) {
            $t->foreignId('office_user_id')->nullable()->after('organization_id')
              ->constrained('users')->nullOnDelete();
            $t->index(['office_user_id', 'discipline', 'year'], 'oc_office_disc_year_idx');
        });

        Schema::table('quota_consumptions', function (Blueprint $t) {
            $t->foreignId('office_user_id')->nullable()->after('organization_id')
              ->constrained('users')->nullOnDelete();
            $t->index(['office_user_id', 'discipline', 'year'], 'qc_office_disc_year_idx');
        });

        Schema::table('office_coalition_members', function (Blueprint $t) {
            $t->foreignId('office_user_id')->nullable()->after('organization_id')
              ->constrained('users')->nullOnDelete();
            $t->index(['office_user_id', 'left_at'], 'ocm_office_left_idx');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->boolean('has_excellence_award')->default(false)->after('is_active');
            $t->boolean('is_bit_khibra')->default(false);
            $t->boolean('has_iso_cert')->default(false);
        });

        // 2. Backfill. Only reachable when tables exist AND models are
        // resolvable — inside a fresh migration this always holds.
        $this->backfillOfficeUserIds();
        $this->backfillBoostFlags();
    }

    public function down(): void
    {
        Schema::table('office_ceilings', function (Blueprint $t) {
            $t->dropIndex('oc_office_disc_year_idx');
            $t->dropForeign(['office_user_id']);
            $t->dropColumn('office_user_id');
        });
        Schema::table('quota_consumptions', function (Blueprint $t) {
            $t->dropIndex('qc_office_disc_year_idx');
            $t->dropForeign(['office_user_id']);
            $t->dropColumn('office_user_id');
        });
        Schema::table('office_coalition_members', function (Blueprint $t) {
            $t->dropIndex('ocm_office_left_idx');
            $t->dropForeign(['office_user_id']);
            $t->dropColumn('office_user_id');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['has_excellence_award', 'is_bit_khibra', 'has_iso_cert']);
        });
    }

    /**
     * Populate office_user_id on existing ceiling/consumption/coalition
     * rows. The mapping is per-organization: pick the lowest-id
     * applicant user as the "primary" office. This is deterministic
     * and safe for the current 1-office-per-org demo state; real
     * multi-office orgs will need manual re-attribution but that's
     * a data-migration concern, not a schema one.
     */
    private function backfillOfficeUserIds(): void
    {
        $primaryOfficeByOrg = [];
        foreach (Organization::all() as $org) {
            $primary = User::where('organization_id', $org->id)
                ->where('role', 'applicant')
                ->orderBy('id')->first();
            if ($primary) $primaryOfficeByOrg[$org->id] = $primary->id;
        }

        foreach (['office_ceilings', 'quota_consumptions', 'office_coalition_members'] as $table) {
            $rows = DB::table($table)->whereNull('office_user_id')->get();
            foreach ($rows as $row) {
                $officeUserId = $primaryOfficeByOrg[$row->organization_id] ?? null;
                if ($officeUserId) {
                    DB::table($table)->where('id', $row->id)->update(['office_user_id' => $officeUserId]);
                }
            }
        }
    }

    /**
     * Copy the three boost flags from each Organization onto EVERY
     * applicant user in that org. If the org has multiple offices,
     * each inherits the same flags (an admin can toggle per-office
     * afterward through the new /admin/offices/{id} endpoint).
     */
    private function backfillBoostFlags(): void
    {
        foreach (Organization::all() as $org) {
            $flags = [
                'has_excellence_award' => (bool) ($org->has_excellence_award ?? false),
                'is_bit_khibra'        => (bool) ($org->is_bit_khibra ?? false),
                'has_iso_cert'         => (bool) ($org->has_iso_cert ?? false),
            ];
            User::where('organization_id', $org->id)
                ->where('role', 'applicant')
                ->update($flags);
        }
    }
};
