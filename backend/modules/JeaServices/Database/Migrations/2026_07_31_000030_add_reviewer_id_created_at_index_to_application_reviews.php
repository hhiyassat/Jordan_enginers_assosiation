<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CS-08 / NEW-A17: index (reviewer_id, created_at) on
 * `application_reviews`.
 *
 * The original migration ships only an index on
 * `(application_id, stage_id)`. `application_reviews.reviewer_id` is
 * a foreign key, and PostgreSQL does NOT auto-index FK columns —
 * the ReviewDashboardController's per-reviewer queries
 *
 *   WHERE reviewer_id = ? AND created_at >= ?
 *   WHERE reviewer_id = ? ORDER BY created_at DESC LIMIT ...
 *
 * therefore fell back to a sequential scan. A composite index keyed
 * on (reviewer_id, created_at) supports both patterns via a single
 * index range scan.
 *
 * The single-column (application_id, stage_id) index already covers
 * the reviewer-lookup path from the *application* side; this new
 * index covers the reviewer-side dashboard queries. No index
 * duplication.
 *
 * Fully reversible via dropIndex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_reviews', function (Blueprint $table) {
            $table->index(['reviewer_id', 'created_at'], 'application_reviews_reviewer_id_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('application_reviews', function (Blueprint $table) {
            $table->dropIndex('application_reviews_reviewer_id_created_at_idx');
        });
    }
};
