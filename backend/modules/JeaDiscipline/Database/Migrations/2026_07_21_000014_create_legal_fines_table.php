<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-82: legal fines per JEA manual Art.14 (p. 251).
 *
 *   "فيعاقب المالك ... بغرامة لا تقل عن (1000) دينار ولا تزيد على
 *    (5000) دينار ... إذا زادت المساحة على ذلك فتصبح الغرامة من
 *    (5000) إلى (50000) دينار."
 *
 * Two tiers keyed on project area:
 *   • small (≤ 250 m²)  — 1,000 to 5,000 JOD
 *   • large (>  250 m²) — 5,000 to 50,000 JOD
 *
 * The MANUAL targets "المالك" — the project OWNER, not the
 * engineering office. Since the platform doesn't model owners as
 * first-class Users, we store:
 *   • target_display  — free-text owner name (from application data
 *                       or admin entry when there's no user account)
 *   • application_id  — optional link to the offending application
 *                       so the audit trail names the project
 *
 * Range validation lives in the controller (not the DB) — the DB
 * accepts any decimal so a future manual amendment doesn't require
 * a migration to change bounds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_fines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $t->string('target_display', 255);          // owner's display name
            $t->string('kind', 32);                     // unlicensed_contractor_small | _large
            $t->unsignedInteger('project_area_m2')->nullable();  // for provenance
            $t->decimal('amount_jod', 12, 2);           // admin picks within kind's range
            $t->text('reason');
            $t->foreignId('issued_by_user_id')->constrained('users');
            $t->timestamp('issued_at');
            $t->timestamp('paid_at')->nullable();
            $t->string('payment_reference', 128)->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['organization_id', 'paid_at']);
            $t->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_fines');
    }
};
