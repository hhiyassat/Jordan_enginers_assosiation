<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-70: office-level ceiling boost flags (JEA 2025 Ch.9 pp. 126).
 *
 * Three optional flags, all default false. QuotaLedger multiplies
 * the office's ceiling by (1 + 0.05*award + 0.05*bit_khibra + 0.05*iso).
 * Every flag defaults false so existing seed data + test fixtures
 * don't silently gain 5-15% extra quota after this migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            // "احتساب 5% من مجموع الحصص عن جائزة الملك عبد الله للتميز"
            $t->boolean('has_excellence_award')->default(false);
            // "احتساب 5% من الحصص لكونه بيت خبرة"
            $t->boolean('is_bit_khibra')->default(false);
            // "احتساب 5% لحصوله على الإيزو (بداية 2007)"
            $t->boolean('has_iso_cert')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $t->dropColumn(['has_excellence_award', 'is_bit_khibra', 'has_iso_cert']);
        });
    }
};
