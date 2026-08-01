<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SG-03 · Bind applications to service_definition_versions.
 *
 * Additive nullable FK. Existing applications remain NULL (classified
 * LEGACY_UNVERSIONED per JDG-SG03-03). Binding happens at submit time
 * per JDG-SG03-02.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('service_definition_version_id')
                ->nullable()
                ->after('service_definition_id')
                ->constrained('service_definition_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_definition_version_id');
        });
    }
};
