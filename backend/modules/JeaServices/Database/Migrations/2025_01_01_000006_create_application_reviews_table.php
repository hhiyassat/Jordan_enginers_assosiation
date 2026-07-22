<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WF-003: Reviewer decisions are stored for full audit trail.
 * FR-010: Approve / reject / request_modifications decisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage_id');                 // schema workflow stage id
            $table->enum('decision', ['approved', 'rejected', 'modifications_requested']);
            $table->text('notes')->nullable();
            $table->json('annotations')->nullable();    // field-level annotations
            $table->unsignedInteger('review_round')->default(1);
            $table->timestamps();

            $table->index(['application_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_reviews');
    }
};
