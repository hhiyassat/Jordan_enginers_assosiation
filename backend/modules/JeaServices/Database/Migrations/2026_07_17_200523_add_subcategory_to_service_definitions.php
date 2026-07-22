<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional grouping label so services under a single parent tile can be
 * visually organised into sections in the UI. Example: JEA-SURV has 14
 * services split into 3 groups — استطلاع الموقع / فحص المواد / الحفريات.
 *
 * Categories that don't need grouping simply leave the fields NULL and
 * render as a flat grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_definitions', function (Blueprint $t) {
            $t->string('subcategory_ar')->nullable()->after('parent_code');
            $t->string('subcategory_en')->nullable()->after('subcategory_ar');
            $t->index(['organization_id', 'parent_code', 'subcategory_ar']);
        });
    }

    public function down(): void
    {
        Schema::table('service_definitions', function (Blueprint $t) {
            $t->dropIndex(['organization_id', 'parent_code', 'subcategory_ar']);
            $t->dropColumn(['subcategory_ar', 'subcategory_en']);
        });
    }
};
