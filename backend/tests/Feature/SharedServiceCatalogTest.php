<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The JEA service catalog is JEA-owned and shared across every office tenant
 * (ServiceCatalogController::index / show, ApplicationController::store).
 *
 * These tests pin the invariant: an applicant in Org B must see (and be able
 * to submit against) services owned by Org A. Regressing to
 * `->where('organization_id', $orgId)` on the read paths leaves every newly
 * approved office with an empty catalog — which is the bug that motivated
 * dropping the org filter in the first place.
 */
class SharedServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Organization $jeaOrg;
    private Organization $officeOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jeaOrg = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeOrg = Organization::create([
            'name_ar' => 'مكتب ب', 'name_en' => 'Office B', 'slug' => 'office-b', 'is_active' => true,
        ]);

        ServiceDefinition::create([
            'organization_id' => $this->jeaOrg->id,
            'code'            => 'SHARED-001',
            'name_ar'         => 'خدمة مشتركة',
            'name_en'         => 'Shared service',
            'schema'          => ['type' => 'object', 'properties' => []],
            'status'          => 'active',
        ]);
    }

    public function test_applicant_in_other_org_sees_jea_owned_service_in_catalog(): void
    {
        $user = User::create([
            'organization_id' => $this->officeOrg->id,
            'name' => 'مهندس', 'email' => 'eng@office-b.test',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/services')->assertOk();
        $codes = collect($res->json('services'))->pluck('code')->all();

        $this->assertContains(
            'SHARED-001',
            $codes,
            'Applicant in Office B must see JEA-owned service in the catalog.'
        );
    }

    public function test_applicant_in_other_org_can_show_jea_owned_service_by_code(): void
    {
        $user = User::create([
            'organization_id' => $this->officeOrg->id,
            'name' => 'مهندس', 'email' => 'eng2@office-b.test',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/services/SHARED-001')
            ->assertOk()
            ->assertJsonPath('service.code', 'SHARED-001');
    }
}
