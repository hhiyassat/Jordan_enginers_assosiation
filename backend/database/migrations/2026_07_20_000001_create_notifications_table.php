<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-9: notifications table.
 *
 * Each row is a per-user inbox item. WorkflowEngine hooks emit
 * notifications at the key transitions (submit, decide, pay, issue).
 * The header bell dropdown reads from here and marks them as read.
 *
 * Schema notes:
 *   • user_id + read_at is the hottest index — the unread-count query
 *     narrows on `WHERE user_id=? AND read_at IS NULL` on every page
 *     load, so it gets a dedicated composite index.
 *   • related_type/id are morphable — link the notification to an
 *     Application (most common) or any other resource without a
 *     dedicated column per type.
 *   • payload is JSON so services can attach arbitrary metadata (the
 *     new certificate number, the reviewer's name, etc.) that the
 *     frontend renders inline.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Event type — small stable set. Frontend maps to icon + colour.
            $t->string('type', 60);

            $t->string('title', 200);
            $t->string('body', 500);

            // Deep link into the app (e.g. /my-applications/123). Nullable
            // because a system-wide announcement may not link anywhere.
            $t->string('link', 250)->nullable();

            // Morphable relation to the source object (Application, ...).
            $t->string('related_type', 60)->nullable();
            $t->unsignedBigInteger('related_id')->nullable();

            $t->json('payload')->nullable();

            $t->timestamp('read_at')->nullable();
            $t->timestamps();

            // Hot path: unread count per user.
            $t->index(['user_id', 'read_at']);
            $t->index(['organization_id', 'created_at']);
            $t->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
