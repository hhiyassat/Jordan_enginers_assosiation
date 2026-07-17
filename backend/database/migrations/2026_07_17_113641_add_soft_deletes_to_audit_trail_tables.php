<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA-004 broader: soft-delete for records whose disappearance would break
 * audit trails or invalidate historical records.
 *
 * Scope:
 *   - service_definitions  — schemas referenced by past applications
 *   - certificates         — issued certs must survive deletion for verification
 *   - application_documents — file references for evidence trail
 *   - projects             — customer-facing entity
 *   - organizations        — multi-tenant root
 *   - integration_cycles   — audit trail with Nashmi
 *
 * SKIPPED (intentionally):
 *   - audit_logs         — append-only by design (DATA-003)
 *   - gsb_call_logs      — has its own retention policy (GsbPruneLogs)
 *   - application_reviews — write-once, no delete semantics
 *   - users, applications — already soft-deleted (NFR-005 + existing code)
 */
return new class extends Migration
{
    private const TABLES = [
        'service_definitions',
        'certificates',
        'application_documents',
        'projects',
        'organizations',
        'integration_cycles',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            if (Schema::hasColumn($tbl, 'deleted_at')) continue;
            Schema::table($tbl, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            if (!Schema::hasColumn($tbl, 'deleted_at')) continue;
            Schema::table($tbl, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
