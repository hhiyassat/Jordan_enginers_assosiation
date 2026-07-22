<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-67: per-engineer per-discipline annual m² quota.
 *
 * Replaces the flat `engineers.annual_quota_m2` column semantically —
 * that column stays in place for backwards compatibility with legacy
 * reads but is no longer authoritative. The Engine\CapacityGuard
 * (JORD-69) reads from this table exclusively.
 *
 * Manual anchor: JEA 2025 Ch.9 pp. 124-125.
 *   Materials engineer:  118,750 m² / year (design)
 *   Structural / electrical: 56,250 m² / year each
 *   (plus classification multipliers set by OfficeCeiling)
 *
 * Composite unique on (engineer_id, discipline, year) prevents double-
 * quota rows for the same engineer/year — a race in the seeder or
 * admin form would otherwise silently double an engineer's cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineer_discipline_quotas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('engineer_id')->constrained()->cascadeOnDelete();
            $t->string('discipline', 32);           // Disciplines::* canonical value
            $t->unsignedSmallInteger('year');       // 2026..
            $t->unsignedInteger('m2_allowed');      // yearly cap
            $t->timestamps();

            $t->unique(['engineer_id', 'discipline', 'year']);
            $t->index(['discipline', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineer_discipline_quotas');
    }
};
