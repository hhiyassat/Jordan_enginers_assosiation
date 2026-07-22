<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-001: Schema-driven engine. The schema JSON column is the source of truth.
 * DATA-002: Service definitions with schema JSON column.
 * BR-005: Workflow stages defined in schema, not hardcoded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();          // e.g. BL-001
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            $table->string('currency', 3)->default('JOD');
            $table->json('schema');                    // source of truth: fields, workflow, fee, documents, certificate
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_definitions');
    }
};
