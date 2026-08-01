<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SG-01 · Add lifecycle and governance columns to service_definitions.
 *
 * See docs/architecture/service-governance/judgments/JDG-SG01-01-lifecycle-model.md
 * for the reasoning chain (الوضع → التوقف).
 *
 * Additive migration only. Existing rows default to `uat_status='NOT_SUBMITTED'`
 * and `publication_status='NOT_PUBLISHED'`. The pre-existing `status` column
 * (active|inactive|draft) is preserved for backward compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_definitions', function (Blueprint $table): void {
            $table->enum('uat_status', ['NOT_SUBMITTED', 'PENDING', 'APPROVED', 'REJECTED'])
                ->default('NOT_SUBMITTED')
                ->after('is_locked');
            $table->string('uat_reference')->nullable()->after('uat_status');
            $table->timestamp('uat_signed_at')->nullable()->after('uat_reference');
            $table->foreignId('uat_signed_by')->nullable()->after('uat_signed_at')
                ->constrained('users')->nullOnDelete();

            $table->enum('publication_status', ['NOT_PUBLISHED', 'PUBLISHED', 'SUSPENDED', 'RETIRED'])
                ->default('NOT_PUBLISHED')
                ->after('uat_signed_by');
            $table->timestamp('published_at')->nullable()->after('publication_status');
            $table->foreignId('published_by')->nullable()->after('published_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('effective_from')->nullable()->after('published_by');

            $table->timestamp('suspended_at')->nullable()->after('effective_from');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')
                ->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('suspended_by');

            $table->timestamp('retired_at')->nullable()->after('suspension_reason');
            $table->foreignId('retired_by')->nullable()->after('retired_at')
                ->constrained('users')->nullOnDelete();
            $table->text('retirement_reason')->nullable()->after('retired_by');

            $table->text('publication_reason')->nullable()->after('retirement_reason');
        });
    }

    public function down(): void
    {
        Schema::table('service_definitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uat_signed_by');
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropConstrainedForeignId('retired_by');
            $table->dropColumn([
                'uat_status', 'uat_reference', 'uat_signed_at',
                'publication_status', 'published_at', 'effective_from',
                'suspended_at', 'suspension_reason',
                'retired_at', 'retirement_reason',
                'publication_reason',
            ]);
        });
    }
};
