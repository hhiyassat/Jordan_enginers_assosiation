<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-79: recurring obligations (registration + annual dues) per
 * JEA manual pp.96-97.
 *
 * One row per (office × kind × period_year). Registration is a
 * one-time obligation created when the office first joins; annual
 * dues are created every February 1 for every active office by
 * the RecurringDuesService cron.
 *
 * Composite unique on (office_user_id, kind, period_year) —
 * DB-level guard against a cron double-run generating duplicate
 * dues rows.
 *
 * The manual's late surcharge (F-05: +15% end-of-Feb→end-of-June,
 * +30% after) is computed at payment time and stored on
 * late_surcharge_jod. Storing avoids ambiguity if the surcharge
 * rate ever changes mid-year — a paid obligation preserves what
 * was actually charged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_obligations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('office_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('kind', 32);                   // 'registration' | 'annual_dues'
            $t->unsignedSmallInteger('period_year');  // 2026..
            $t->string('period_label_ar', 128)->nullable();
            $t->decimal('amount_jod', 12, 2);         // base amount owed
            $t->date('due_date');                     // typically end of Feb for annual_dues
            $t->timestamp('paid_at')->nullable();
            $t->string('payment_reference', 128)->nullable();
            $t->decimal('late_surcharge_jod', 12, 2)->default(0);
            $t->decimal('total_paid_jod', 12, 2)->nullable(); // amount + late_surcharge
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['office_user_id', 'kind', 'period_year']);
            $t->index(['office_user_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_obligations');
    }
};
