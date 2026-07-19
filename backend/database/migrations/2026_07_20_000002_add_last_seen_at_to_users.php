<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-24: users.last_seen_at powers admin online/idle/offline
 * presence indicators.
 *
 * Bumped by the TrackUserActivity middleware on every authenticated
 * request. Presence buckets:
 *   • online  — bumped within the last 5 minutes
 *   • idle    — bumped within the last 30 minutes
 *   • offline — otherwise (or null = never seen since login)
 *
 * Indexed so the presence controller can compute buckets without a
 * full table scan on large orgs. The read pattern is
 * "WHERE organization_id = ? AND last_seen_at >= ?".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('last_seen_at')->nullable()->after('password_changed_at');
            $t->index(['organization_id', 'last_seen_at'], 'users_org_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('users_org_last_seen_idx');
            $t->dropColumn('last_seen_at');
        });
    }
};
