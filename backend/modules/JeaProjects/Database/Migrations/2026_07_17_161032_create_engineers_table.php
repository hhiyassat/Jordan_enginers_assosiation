<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engineers registered under an engineering office (applicant user).
 * Each engineer has their own annual m² quota; projects are attributed
 * to a specific engineer and quota is enforced at the engineer level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('office_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name_ar');
            $t->string('name_en')->nullable();
            $t->string('membership_number');
            $t->string('specialization')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->unsignedInteger('annual_quota_m2')->nullable();
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            $t->timestamps();

            $t->unique(['office_user_id', 'membership_number']);
            $t->index(['office_user_id', 'is_active']);
            $t->index(['organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineers');
    }
};
