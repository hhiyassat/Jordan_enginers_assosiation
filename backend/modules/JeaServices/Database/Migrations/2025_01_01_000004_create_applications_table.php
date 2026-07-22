<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA-001: JSON data column — no new migration per service.
 * WF-001: status column maps to ALLOWED_TRANSITIONS state machine.
 * WF-008: sla_deadline tracks SLA per stage.
 * BR-004: organization_id enforces multi-tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();   // e.g. ESP-BL-001-20260706-0001
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            // WF-001 — state machine
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'modifications_requested',
                'approved',
                'rejected',
                'certificate_issued',
            ])->default('draft');

            $table->string('current_stage')->nullable();   // maps to schema workflow stage id

            // DATA-001 — form data
            $table->json('data')->nullable();

            // Fee tracking
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'waived'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();

            // WF-008 — SLA
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('sla_breached_at')->nullable();

            // Modification tracking
            $table->unsignedInteger('review_round')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['applicant_id', 'status']);
            $table->index(['assigned_reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
