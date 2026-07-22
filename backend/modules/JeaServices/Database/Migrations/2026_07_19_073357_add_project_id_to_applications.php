<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable project_id FK on applications. Present when the applicant
 * reached the Apply form via /projects/{id}/… — the Apply page then renders
 * the project's fields (name, contract, request, area, city, type) as a
 * read-only header instead of asking the applicant to re-type them.
 *
 * Nullable because not every service is project-scoped: certificates,
 * financial requests, board complaints, etc. carry no project context.
 * The FK is nullOnDelete so soft-deleting a project doesn't cascade into
 * applications — the historical link stays visible via withTrashed().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('service_definition_id')
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
