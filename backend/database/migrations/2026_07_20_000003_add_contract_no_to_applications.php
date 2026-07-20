<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-14: applications.contract_no.
 *
 * Prior semantics: contract_no lived only on Project. An application
 * linked to a project had no direct way to surface the contract
 * number without joining. The review flagged that as confusing —
 * the applicant thinks in "contracts", not in "reference numbers".
 *
 * New semantics: on create, an application that carries a project_id
 * copies the project's contract_no into its own column. Applications
 * without a project (rare — some standalone services) leave it null.
 * Indexed so the admin can search "which apps belong to contract X".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $t) {
            $t->string('contract_no', 50)->nullable()->after('reference_number');
            $t->index('contract_no', 'applications_contract_no_idx');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $t) {
            $t->dropIndex('applications_contract_no_idx');
            $t->dropColumn('contract_no');
        });
    }
};
