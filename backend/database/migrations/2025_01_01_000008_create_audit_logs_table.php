<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WF-003: Append-only audit log for every state mutation.
 * DATA-003: No UPDATE or DELETE — insert only.
 * SEC-007: input_snapshot stores non-sensitive fields only.
 * NFR-006: Audit log retention 7 years.
 *
 * EDA B-9: effect_recorded — every transition writes one row here.
 * EDA §9.2: is_manual_override flag for admin overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Polymorphic subject (Application, User, ServiceDefinition, ...)
            $table->nullableMorphs('auditable');

            $table->string('action');                   // e.g. 'application.submitted'
            $table->string('rule_id')->nullable();       // BRR reference e.g. 'ESP-WF-001'
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('input_snapshot')->nullable();  // non-sensitive fields only
            $table->json('extra')->nullable();           // additional context

            // §9.2 override tracking
            $table->boolean('is_manual_override')->default(false);
            $table->foreignId('override_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();  // no updated_at — append-only

            $table->index(['organization_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
