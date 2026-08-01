<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SG-03 · Create the immutable service_definition_versions table.
 *
 * See docs/architecture/service-governance/judgments/JDG-SG03-01-snapshot-scope.md
 * for the reasoning chain (schema-only snapshot; extension-declaration
 * snapshotting deferred to RES-SG03-01).
 *
 * Immutability is enforced at the model layer, not the database — Postgres
 * has no per-row immutability primitive. The model's saving observer
 * refuses updates to a published version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_definition_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_definition_id')
                ->constrained('service_definitions')
                ->cascadeOnDelete();
            $table->string('version_identifier'); // e.g. "v1.0.0-2026-08-01"
            $table->json('schema_snapshot');       // immutable copy of service_definitions.schema at publish time
            $table->string('schema_hash', 64);     // SHA-256 hex of canonical JSON — collision-check on future republish

            $table->enum('status', ['DRAFT', 'PUBLISHED', 'SUPERSEDED', 'RETIRED'])->default('DRAFT');

            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_reference')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('supersedes_version_id')
                ->nullable()
                ->constrained('service_definition_versions')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['service_definition_id', 'version_identifier']);
            $table->index(['service_definition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_definition_versions');
    }
};
