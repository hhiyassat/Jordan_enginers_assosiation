<?php

namespace Tests\Feature;

use App\Engine\Disciplines;
use App\Engine\QuotaLedger;
use App\Models\Engineer;
use App\Models\EngineerDisciplineQuota;
use App\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-70: office + engineer ceiling/quota boosts. Pins the exact
 * stacking rule (additive, not multiplicative) so a future edit
 * to QuotaLedger doesn't silently switch to compounding percentages.
 *
 * Manual quotes:
 *   • "احتساب 5% من مجموع الحصص عن جائزة الملك عبد الله للتميز" (Q-06)
 *   • "احتساب 5% لكونه بيت خبرة" + "5% لحصوله على الإيزو" (Q-07)
 *   • "المهندس المسجل رئيساً للاختصاص ... يمنح زيادة بنسبة 20%" (Q-08)
 */
class QuotaBoostsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private Engineer $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeUser = User::create([
            'organization_id' => $this->org->id, 'name' => 'o', 'email' => 'o@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م', 'membership_number' => 'EN-001',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        EngineerDisciplineQuota::create([
            'engineer_id' => $this->engineer->id,
            'discipline'  => Disciplines::ARCHITECTURAL,
            'year'        => (int) now()->year,
            'm2_allowed'  => 10000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id,
            'discipline'      => Disciplines::ARCHITECTURAL,
            'year'            => (int) now()->year,
            'm2_allowed'      => 20000,
        ]);
    }

    public function test_default_flags_produce_unboosted_quotas(): void
    {
        // Regression: after this migration lands, existing orgs / engineers
        // must NOT silently gain quota. Every flag defaults false → the
        // "no-boost" path must return the exact seeded value.
        $this->assertSame(10000, app(QuotaLedger::class)->remainingEngineerQuota(
            $this->engineer, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
        $this->assertSame(20000, app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->org->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }

    public function test_specialization_head_gives_engineer_plus_20_percent(): void
    {
        // 10,000 × 1.20 = 12,000 effective.
        $this->engineer->update(['is_specialization_head' => true]);

        $this->assertSame(12000, app(QuotaLedger::class)->remainingEngineerQuota(
            $this->engineer, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }

    public function test_office_award_gives_ceiling_plus_5_percent(): void
    {
        // 20,000 × 1.05 = 21,000.
        $this->org->update(['has_excellence_award' => true]);
        $this->assertSame(21000, app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->org->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }

    public function test_bit_khibra_alone_gives_plus_5_percent(): void
    {
        $this->org->update(['is_bit_khibra' => true]);
        $this->assertSame(21000, app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->org->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }

    public function test_iso_alone_gives_plus_5_percent(): void
    {
        $this->org->update(['has_iso_cert' => true]);
        $this->assertSame(21000, app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->org->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }

    public function test_office_boosts_stack_additively_not_multiplicatively(): void
    {
        // All 3 flags on: 20,000 × (1.0 + 0.05 + 0.05 + 0.05) = 23,000.
        // Multiplicative stacking would give 20,000 × 1.05^3 = 23,152.5
        // — pin the additive semantic so an accidental refactor to
        // compounding percentages gets caught here.
        $this->org->update([
            'has_excellence_award' => true,
            'is_bit_khibra'        => true,
            'has_iso_cert'         => true,
        ]);
        $this->assertSame(23000, app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->org->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }

    public function test_engineer_and_office_boosts_apply_independently(): void
    {
        // Engineer: 10,000 × 1.20 = 12,000
        // Office:   20,000 × 1.15 = 23,000
        // The two live in different tables and both apply — pin the
        // independence so a future refactor doesn't accidentally
        // apply engineer boost to office ceiling or vice versa.
        $this->engineer->update(['is_specialization_head' => true]);
        $this->org->update([
            'has_excellence_award' => true,
            'is_bit_khibra'        => true,
            'has_iso_cert'         => true,
        ]);
        $this->assertSame(12000, app(QuotaLedger::class)->remainingEngineerQuota(
            $this->engineer, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
        $this->assertSame(23000, app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->org->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        ));
    }
}
