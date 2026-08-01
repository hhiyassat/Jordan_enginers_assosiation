<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SG-04 · rule_definitions + rule_versions + calculation_snapshots.
 *
 * See docs/architecture/service-governance/judgments/JDG-SG04-01 for the
 * granularity decision (per-calculator) and JDG-SG04-02 for the write policy
 * (DRAFT overwrite, SUBMIT/MANUAL_RECALC immutable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_identifier')->unique(); // e.g. SRV001_EXPLORATION_MATRIX
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_definition_id')
                ->constrained('rule_definitions')
                ->cascadeOnDelete();
            $table->string('version_identifier'); // e.g. v1.0.0-2026-08-01
            $table->string('implementation_identity'); // e.g. Modules\JeaServices\Engine\WellsCountCalculator
            $table->text('source_reference'); // human-readable citation
            $table->enum('business_approval_status', ['APPROVED', 'PROVISIONAL', 'REJECTED', 'PENDING'])
                ->default('PROVISIONAL');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['rule_definition_id', 'version_identifier']);
            $table->index(['rule_definition_id', 'business_approval_status']);
        });

        Schema::create('calculation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            $table->foreignId('rule_version_id')
                ->constrained('rule_versions');
            $table->enum('purpose', ['DRAFT', 'SUBMIT', 'MANUAL_RECALC'])->default('DRAFT');
            $table->json('inputs');
            $table->json('outputs');
            $table->json('intermediate_values')->nullable();
            $table->json('warnings')->nullable();
            $table->json('open_decisions')->nullable();
            $table->foreignId('superseded_snapshot_id')
                ->nullable()
                ->constrained('calculation_snapshots')
                ->nullOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at');
            $table->timestamps();

            // For the DRAFT overwrite path we need to find the current row quickly.
            $table->unique(['application_id', 'rule_version_id', 'purpose'], 'snap_app_rule_purpose_unique');
            $table->index(['application_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_snapshots');
        Schema::dropIfExists('rule_versions');
        Schema::dropIfExists('rule_definitions');
    }
};
