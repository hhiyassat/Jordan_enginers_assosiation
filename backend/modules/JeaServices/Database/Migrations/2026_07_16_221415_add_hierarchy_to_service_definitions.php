<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds hierarchy + display columns so services can be grouped under a
 * parent "folder" tile (e.g. مشاريعي → 7 drawing services) and so the
 * catalog API can serve fee/SLA without unpacking the schema JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_definitions', function (Blueprint $table) {
            $table->string('parent_code')->nullable()->after('code');
            $table->decimal('base_fee', 10, 2)->nullable()->after('currency');
            $table->unsignedInteger('sla_hours')->nullable()->after('base_fee');

            $table->index(['organization_id', 'parent_code']);
        });
    }

    public function down(): void
    {
        Schema::table('service_definitions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'parent_code']);
            $table->dropColumn(['parent_code', 'base_fee', 'sla_hours']);
        });
    }
};
