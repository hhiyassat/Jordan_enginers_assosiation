<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-73: office_coalition membership pivot with join/leave timestamps.
 *
 * An organization may belong to at most ONE active coalition at a time.
 * "Active" = coalition.dissolved_at is null AND membership.left_at is null.
 * The composite unique on (coalition_id, organization_id) prevents an
 * office from being listed twice in the same coalition; the app-level
 * check enforces one-coalition-at-a-time across all coalitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_coalition_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('office_coalition_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->timestamp('joined_at')->nullable();
            $t->timestamp('left_at')->nullable();
            $t->timestamps();

            $t->unique(['office_coalition_id', 'organization_id']);
            $t->index(['organization_id', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_coalition_members');
    }
};
