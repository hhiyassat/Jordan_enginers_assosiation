<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-73: office coalitions (ائتلاف) per JEA 2025 Ch.9 pp. 131 + 136.
 *
 * A coalition is a set of engineering offices pooling their annual
 * quotas + per-project caps for a joint tender or major project.
 * The manual specifies:
 *   • Per-project cap for the coalition = 1.5 × mean(member caps)
 *   • Coalition per-discipline ceiling = ((n-0.5)/n) × sum(member ceilings)
 *   • Quota-transfer percentages by discipline + location
 *     (electrical/mechanical: 20% Amman, 30% other; architectural/
 *      structural: 40%/50%)
 *
 * The coalition itself is just an id + human-readable name;
 * membership + timing live on office_coalition_members.
 *
 * dissolved_at flags terminal state — a dissolved coalition is
 * ignored for capacity checks even if members still reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_coalitions', function (Blueprint $t) {
            $t->id();
            $t->string('name_ar')->nullable();
            $t->string('name_en')->nullable();
            $t->timestamp('formed_at')->nullable();
            $t->timestamp('dissolved_at')->nullable();
            $t->timestamps();

            $t->index(['dissolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_coalitions');
    }
};
