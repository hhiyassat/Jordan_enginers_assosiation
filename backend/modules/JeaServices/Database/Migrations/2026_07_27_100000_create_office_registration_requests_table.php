<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public office-registration submissions.
 *
 * See docs/architecture/office-registration-flow.md for the flow rationale.
 * Distinct from the applications table because these rows exist BEFORE
 * any Organization / User exists — they represent a request to CREATE
 * those entities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_registration_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference_number', 32)->unique();

            // Office info (becomes Organization on approval)
            $t->string('office_name_ar',   200);
            $t->string('office_name_en',   200);
            $t->string('office_license_no', 50)->unique();
            $t->string('office_city',      100);
            $t->string('office_address_ar', 500);
            $t->string('office_phone',      30);
            $t->string('office_email');

            // Engineers submitted with the office (array of
            // {name_ar, name_en, membership_number, specialization})
            $t->json('engineers');

            // Review state
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->text('review_notes')->nullable();
            $t->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $t->timestamp('reviewed_at')->nullable();

            // On approval: link to the created Organization
            $t->unsignedBigInteger('approved_organization_id')->nullable();

            // Provenance / anti-spam
            $t->ipAddress('submitter_ip')->nullable();
            $t->string('submitter_user_agent', 500)->nullable();

            $t->softDeletes();
            $t->timestamps();

            $t->index('status');
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_registration_requests');
    }
};
