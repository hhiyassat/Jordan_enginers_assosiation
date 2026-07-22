<?php

namespace Tests\Feature;

use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaProjects\Engine\QuotaLedger;
use App\Models\Application;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\OfficeCeiling;
use Modules\JeaProjects\Models\OfficeCoalition;
use Modules\JeaProjects\Models\OfficeCoalitionMember;
use App\Models\Organization;
use Modules\JeaProjects\Models\QuotaConsumption;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-73: office coalitions (ائتلاف) per JEA Ch.9 pp.131 + 136.
 *
 * Pins the two arithmetic invariants the manual actually prices:
 *   • coalition_ceiling = ((n-0.5)/n) × Σ(member_ceilings)  [Q-11]
 *   • coalition_per_project_cap = 1.5 × mean(member caps)   [Q-11]
 *
 * Plus the semantic guardrails: coalition consumption sums across
 * all members; standalone orgs are unaffected; dissolved coalitions
 * are ignored; the same office can't be in two active coalitions.
 */
class OfficeCoalitionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $officeUser;
    private User $officeUserB;
    private Engineer $engineer;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::create([
            'name_ar' => 'A', 'name_en' => 'A', 'slug' => 'a', 'is_active' => true,
        ]);
        $this->orgB = Organization::create([
            'name_ar' => 'B', 'name_en' => 'B', 'slug' => 'b', 'is_active' => true,
        ]);
        // JORD-77: coalitions aggregate per office_user_id, so each
        // Organization needs its OWN applicant User to be a coalition
        // member (an "office" in JEA's data model = a User w/ role=applicant).
        $this->officeUser = User::create([
            'organization_id' => $this->orgA->id, 'name' => 'oA', 'email' => 'oa@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->officeUserB = User::create([
            'organization_id' => $this->orgB->id, 'name' => 'oB', 'email' => 'ob@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->orgA->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م', 'membership_number' => 'EN-001',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        // Seed ceilings for both offices so the coalition math has data.
        // A = 10,000 architectural. B = 20,000 architectural.
        // Coalition sum = 30,000. With n=2 → ((2-0.5)/2) × 30,000 = 22,500.
        OfficeCeiling::create([
            'organization_id' => $this->orgA->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 10000,
            'per_project_cap_m2' => 3000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->orgB->id,
            'office_user_id'  => $this->officeUserB->id,
            'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 20000,
            'per_project_cap_m2' => 5000,
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->orgA->id, 'code' => 'DRW-P-TEST',
            'name_ar' => 't', 'name_en' => 't', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => []]],
        ]);
    }

    public function test_standalone_org_ceiling_matches_pre_coalition_behavior(): void
    {
        // Regression: no coalition = same 10,000 remaining as JORD-70.
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        $this->assertSame(10000, $rem);
    }

    public function test_coalition_ceiling_uses_n_minus_half_over_n_formula(): void
    {
        // A + B in coalition: ((2-0.5)/2) × (10,000 + 20,000) = 22,500.
        $this->formCoalition([$this->officeUser, $this->officeUserB]);
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        $this->assertSame(22500, $rem);
    }

    public function test_coalition_consumption_sums_across_all_members(): void
    {
        // A + B in coalition. B consumes 5,000 → coalition remaining
        // is 22,500 - 5,000 = 17,500 when A queries too.
        $this->formCoalition([$this->officeUser, $this->officeUserB]);
        $priorApp = $this->makeApp($this->orgB, ['area_m2' => 5000]);
        QuotaConsumption::create([
            'application_id' => $priorApp->id,
            'engineer_id'    => $this->engineer->id,
            'organization_id' => $this->orgB->id,
            'office_user_id'  => $this->officeUserB->id,
            'discipline'     => Disciplines::ARCHITECTURAL,
            'year'           => (int) now()->year, 'm2' => 5000,
        ]);
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        $this->assertSame(17500, $rem);
    }

    public function test_dissolved_coalition_falls_back_to_standalone_ceiling(): void
    {
        $coalition = $this->formCoalition([$this->officeUser, $this->officeUserB]);
        $coalition->update(['dissolved_at' => now()->subDay()]);

        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        // Back to standalone A ceiling = 10,000.
        $this->assertSame(10000, $rem);
    }

    public function test_left_member_falls_back_to_standalone_ceiling(): void
    {
        $coalition = $this->formCoalition([$this->officeUser, $this->officeUserB]);
        // A leaves — its membership row gets left_at.
        OfficeCoalitionMember::where('office_coalition_id', $coalition->id)
            ->where('office_user_id', $this->officeUser->id)
            ->update(['left_at' => now()->subHour()]);

        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        $this->assertSame(10000, $rem, 'A is no longer in the coalition, gets its own ceiling');
    }

    public function test_coalition_per_project_cap_is_15x_mean_of_member_caps(): void
    {
        // A cap 3,000, B cap 5,000 → mean 4,000 × 1.5 = 6,000.
        // Submit 8,000 → excess 2,000 → surcharge = 0.25 × 2,000 × rate.
        $this->formCoalition([$this->officeUser, $this->officeUserB]);
        // Give the service a per_unit fee at 3.5/m² so the surcharge
        // decomposes cleanly.
        $this->service->update(['schema' => array_merge($this->service->schema, [
            'fields' => [['id' => 'area_m2', 'label_ar' => 'م', 'type' => 'number']],
            'fee'    => ['type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 3.5, 'currency' => 'JOD'],
        ])]);

        $app = $this->makeApp($this->orgA, [
            'engineer_id' => $this->engineer->id, 'area_m2' => 8000,
        ]);
        $surcharge = app(QuotaLedger::class)->overflowSurchargeFor($app);

        $this->assertNotNull($surcharge);
        // 25% × 2,000 excess × 3.5 = 1,750.
        $this->assertSame(1750.00, $surcharge['amount']);
        $this->assertStringContainsString('2000', $surcharge['label_ar']);
    }

    public function test_coalition_with_missing_member_ceilings_is_gracefully_handled(): void
    {
        // B lacks a mechanical ceiling; asking about mechanical when A
        // also lacks one → null (no ceiling configured for the group).
        $this->formCoalition([$this->officeUser, $this->officeUserB]);
        $this->assertNull(app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::MECHANICAL, (int) now()->year,
        ));
    }

    /**
     * @param  list<User> $officeUsers  the per-office applicants that join
     *                                  the coalition (JORD-77: membership
     *                                  is keyed on office_user_id, not org).
     */
    private function formCoalition(array $officeUsers): OfficeCoalition
    {
        $coalition = OfficeCoalition::create([
            'name_ar' => 'ائتلاف اختبار', 'name_en' => 'Test coalition',
            'formed_at' => now(),
        ]);
        foreach ($officeUsers as $officeUser) {
            OfficeCoalitionMember::create([
                'office_coalition_id' => $coalition->id,
                'organization_id'     => $officeUser->organization_id,
                'office_user_id'      => $officeUser->id,
                'joined_at'           => now(),
            ]);
        }
        return $coalition;
    }

    private function makeApp(Organization $org, array $data): Application
    {
        // Applicant on the app IS the office user; that's what QuotaLedger
        // reads to key the office_user_id lookup post-JORD-77.
        $applicantId = $org->id === $this->orgB->id ? $this->officeUserB->id : $this->officeUser->id;
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $applicantId,
            'status'                => Application::STATUS_APPROVED,
            'data'                  => $data,
            'fee_amount'            => 0,
        ]);
    }
}
