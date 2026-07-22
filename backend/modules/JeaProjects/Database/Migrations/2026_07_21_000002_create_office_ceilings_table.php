<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-67: per-organization (office) per-discipline annual m² ceiling.
 *
 * The office-level cap sits ABOVE the sum of its engineers' quotas —
 * even if an office employs three engineers each with 56,250 m²
 * structural, the office's own ceiling (per its classification tier)
 * gates the total. Both must have room for a submission to be accepted.
 *
 * Manual anchor: JEA 2025 Ch.9 pp. 127 (السقوف الهندسية).
 *   Class-B engineer office: 15,000-30,000 m² structural
 *   Consultant office:       45,000-90,000 m² structural
 *
 * Composite unique on (organization_id, discipline, year) — same
 * rationale as the engineer quota table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_ceilings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('discipline', 32);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('m2_allowed');
            $t->timestamps();

            $t->unique(['organization_id', 'discipline', 'year']);
            $t->index(['discipline', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_ceilings');
    }
};
