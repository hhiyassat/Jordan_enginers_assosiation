<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-83: supervision transfers per JEA manual p.30 (C-07).
 *
 *   "عقود والتزامات المكاتب الموقوفة عن مزاولة المهنة بناءً على
 *    قرار مجلس تأديبي: ... المشاريع التي يقوم المكتب الهندسي
 *    بالاشراف عليها الحالياً ... يتم استكمال متابعة عقود الاشراف
 *    من خلال مكتب اخر ويطلب حضور تعهد من مكتب لمتابعة اعمال
 *    الاشراف."
 *
 * When an office is deregistered or long-suspended (2yr), every one
 * of their approved-but-not-cert-issued drawings loses its current
 * supervisor. This table records the resulting "needs transfer"
 * queue — one row per application. Admin picks a target office
 * (source_office_user_id stays for provenance); the target office
 * takes over supervision without paying the transfer fee (waived
 * per the manual).
 *
 * Statuses:
 *   • pending  — auto-created on sanction, awaiting admin assignment
 *   • assigned — admin picked a target office; awaiting acceptance
 *   • accepted — target office accepted; supervision transferred
 *   • declined — target office refused; back to pending for reassignment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('source_office_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('target_office_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('triggering_sanction_id')->nullable()->constrained('sanctions')->nullOnDelete();
            $t->string('status', 32)->default('pending');
            $t->boolean('fee_waived')->default(true);   // C-07 free-tier waiver
            $t->text('notes')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->softDeletes();

            // One transfer per application at a time — DB-level guard
            // against a duplicate open transfer for the same app.
            $t->unique('application_id');
            $t->index(['organization_id', 'status']);
            $t->index('target_office_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_transfers');
    }
};
