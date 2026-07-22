<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects owned by an engineering office (applicant user). Each project is a
 * container for service applications — e.g. مشاريعي → project X → drawings services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('type')->nullable();                       // e.g. سكني / تجاري / صناعي / حكومي
            $table->unsignedInteger('area_m2')->nullable();
            $table->string('city')->nullable();
            $table->string('contract_no')->nullable();
            $table->string('request_no')->nullable();
            $table->enum('status', ['active', 'pending', 'archived'])->default('pending');
            $table->timestamps();

            $table->index(['organization_id', 'owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
