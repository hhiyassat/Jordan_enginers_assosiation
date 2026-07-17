<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an annual square-metre quota to each engineering-office user.
 *
 * Business rule: each office has a maximum total area of projects they may
 * register in one calendar year. used_m2 = sum(area_m2) of the office's
 * projects created between Jan 1 and today. The engine enforces this on
 * POST /api/v1/projects (see ProjectController::store).
 *
 * NULL means "no quota / unlimited" — staff, auditors, and admins fall in
 * this category by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedInteger('annual_quota_m2')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('annual_quota_m2');
        });
    }
};
