<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Engineer;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-12: quota lives at the OFFICE level, not per-engineer. Any
 * engineer under the office draws from the same pooled bucket, and
 * totals returned by GET /projects/quota come from
 * users.annual_quota_m2 + SUM(office projects), not from the sum of
 * per-engineer quotas.
 */
class OfficeQuotaPoolingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $office;
    private Engineer $eng1;
    private Engineer $eng2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-pool-' . uniqid(),
            'is_active' => true,
        ]);
        $this->office = User::create([
            'organization_id'    => $this->org->id,
            'name'               => 'office',
            'email'              => 'office-pool@t.esp',
            'password'           => Hash::make('Secret123!'),
            'role'               => 'applicant',
            'is_active'          => true,
            'password_changed_at' => now(),
            'annual_quota_m2'    => 1000,
        ]);
        $this->eng1 = Engineer::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->office->id,
            'name_ar' => 'م1', 'name_en' => 'e1',
            'membership_number' => 'M-1',
            'is_active' => true,
            'annual_quota_m2' => 500,
        ]);
        $this->eng2 = Engineer::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->office->id,
            'name_ar' => 'م2', 'name_en' => 'e2',
            'membership_number' => 'M-2',
            'is_active' => true,
            'annual_quota_m2' => 500,
        ]);
    }

    private function createProject(int $engineerId, int $area): array
    {
        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/projects', [
            'engineer_id' => $engineerId,
            'name_ar'     => 'p-' . uniqid(),
            'area_m2'     => $area,
        ]);
        return [$res, $res->status()];
    }

    public function test_totals_come_from_office_quota_not_summed_engineer_quotas(): void
    {
        // Two engineers with 500 each would previously sum to 1000 in
        // totals — coincidentally correct. Make the sum diverge from
        // the office quota to prove which one wins.
        $this->office->update(['annual_quota_m2' => 700]);
        Sanctum::actingAs($this->office);

        $res = $this->getJson('/api/v1/projects/quota');
        $res->assertOk()
            ->assertJsonPath('totals.quota_m2', 700)
            ->assertJsonPath('totals.used_m2', 0);
    }

    public function test_project_by_second_engineer_counts_against_pooled_office_quota(): void
    {
        // First engineer spends 800 of the 1000 office pool.
        [$r1, $s1] = $this->createProject($this->eng1->id, 800);
        $this->assertSame(201, $s1, $r1->getContent());

        // Second engineer tries to spend 300 more — total would be
        // 1100 > 1000. Must fail even though eng2's own limit is 500.
        [$r2, $s2] = $this->createProject($this->eng2->id, 300);
        $this->assertSame(422, $s2, $r2->getContent());
        $r2->assertJsonPath('quota_exceeded', true)
           ->assertJsonPath('quota', 1000)
           ->assertJsonPath('used', 800)
           ->assertJsonPath('remaining', 200);
    }

    public function test_totals_used_sums_across_every_office_project(): void
    {
        [$r1, ] = $this->createProject($this->eng1->id, 200);
        [$r2, ] = $this->createProject($this->eng2->id, 150);
        $this->assertSame(201, $r1->status());
        $this->assertSame(201, $r2->status());

        Sanctum::actingAs($this->office);
        $res = $this->getJson('/api/v1/projects/quota');
        $res->assertOk()
            ->assertJsonPath('totals.used_m2', 350)
            ->assertJsonPath('totals.remaining_m2', 650);
    }

    public function test_null_office_quota_means_unlimited(): void
    {
        $this->office->update(['annual_quota_m2' => null]);
        Sanctum::actingAs($this->office);

        // Big project — no cap should reject.
        [$res, ] = $this->createProject($this->eng1->id, 999999);
        $this->assertSame(201, $res->status(), $res->getContent());

        $quota = $this->getJson('/api/v1/projects/quota');
        $quota->assertJsonPath('totals.quota_m2', null)
              ->assertJsonPath('totals.unlimited', true);
    }
}
