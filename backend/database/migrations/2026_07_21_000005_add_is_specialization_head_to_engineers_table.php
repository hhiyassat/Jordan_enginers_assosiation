<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-70: engineer-level +20% quota boost when the engineer is
 * registered as head-of-specialization for their office (JEA 2025
 * p. 125 — "المهندس المسجل رئيساً للاختصاص لدى المكتب يمنح زيادة
 * بنسبة 20% من الحصص الهندسية").
 *
 * Default false — pre-existing seeded engineers don't silently gain
 * 20% quota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engineers', function (Blueprint $t) {
            $t->boolean('is_specialization_head')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('engineers', function (Blueprint $t) {
            $t->dropColumn('is_specialization_head');
        });
    }
};
