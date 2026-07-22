<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-81: complaints + sanctions per JEA manual Ch.7 (pp.11-13,
 * 250, 272, 278, 280) — the disciplinary spine that closes rules
 * C-01, C-02, C-03, C-04, C-06, C-08 from the source doc.
 *
 * Two tables, kept separate because the objects have different
 * lifecycles:
 *
 *   complaints — one row per intake. Might be dismissed, might
 *   escalate to sanction. Reporter can be anonymous (nullable
 *   reporter_user_id) since the manual allows outside parties
 *   (municipality, competing office, citizen) to file.
 *
 *   sanctions — one row per issued penalty. May outlive the
 *   complaint that spawned it (audit trail lives here); enforced
 *   by SanctionGuard on submission.
 *
 * Sanction kinds encode the manual's escalation ladder:
 *   • warning              — logged, no operational impact
 *   • suspension_1yr       — C-03 classification/registration violations
 *   • suspension_2yr       — C-04 endangering-safety violations
 *   • deregistration       — permanent removal (Art.14 escalations)
 *
 * effective_from + effective_until define the active window;
 * SanctionGuard treats now() in [from, until] as "active".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // The office being complained about.
            $t->foreignId('target_office_user_id')->constrained('users')->cascadeOnDelete();
            // The person filing the complaint. Nullable because the
            // manual (p.278) allows outside parties (citizens,
            // municipality) whose accounts we may not have.
            $t->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reporter_display', 128)->nullable(); // when reporter is external
            $t->string('kind', 32);                          // fee_undercutting / contracting_ban / safety_violation / other
            $t->text('description');
            $t->string('status', 32)->default('open');       // open / investigating / decided / dismissed
            $t->date('investigation_deadline');              // C-01: 30 days from intake
            $t->timestamp('decided_at')->nullable();
            $t->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('decision_notes')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['target_office_user_id', 'status']);
            $t->index('investigation_deadline');
        });

        Schema::create('sanctions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('office_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 32);                          // warning / suspension_1yr / suspension_2yr / deregistration
            $t->date('effective_from');
            $t->date('effective_until')->nullable();         // null = deregistration (permanent) OR warning (informational)
            $t->text('reason');
            $t->foreignId('issued_by_user_id')->constrained('users');
            $t->timestamps();
            $t->softDeletes();

            $t->index(['office_user_id', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('complaints');
    }
};
