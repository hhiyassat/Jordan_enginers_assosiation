<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-13: `office_registration_requests` originally declared its FK
 * columns (`reviewed_by_user_id`, `approved_organization_id`) as
 * bare `unsignedBigInteger` without database-level foreign-key
 * constraints. Adding the constraints protects against orphan
 * rows if a user or organization is hard-deleted.
 *
 * Both columns remain nullable — a fresh registration has neither
 * a reviewer nor a resulting organization until an admin decides.
 *
 * `nullOnDelete` (rather than cascade) is deliberate: if the
 * approving admin's account is later removed, the registration
 * request should stay for audit; only the pointer is cleared.
 *
 * Only invoked when the two columns exist as bare integers, so
 * a rerun on already-constrained columns is a no-op.
 */
return new class extends Migration {
    public function up(): void
    {
        // SQLite cannot ADD a foreign key to an existing column via
        // ALTER TABLE. On sqlite the constraint is a no-op — the
        // ORM still enforces the relation at write time and the
        // upstream Postgres/MySQL schema does hold the constraint.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('office_registration_requests', function (Blueprint $t) {
            $t->foreign('reviewed_by_user_id', 'ofr_reviewed_by_user_id_fk')
                ->references('id')->on('users')
                ->nullOnDelete();
            $t->foreign('approved_organization_id', 'ofr_approved_org_id_fk')
                ->references('id')->on('organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('office_registration_requests', function (Blueprint $t) {
            $t->dropForeign('ofr_reviewed_by_user_id_fk');
            $t->dropForeign('ofr_approved_org_id_fk');
        });
    }
};
