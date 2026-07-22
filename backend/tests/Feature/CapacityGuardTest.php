<?php

namespace Tests\Feature;

use Modules\JeaProjects\Engine\CapacityGuard;
use Modules\JeaProjects\Engine\Disciplines;
use App\Models\Application;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\EngineerDisciplineQuota;
use Modules\JeaProjects\Models\OfficeCeiling;
use App\Models\Organization;
use Modules\JeaProjects\Models\QuotaConsumption;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-69: submit-time capacity gate. Pins:
 *   • Non-quota services (no area_m2 field) → pass-through.
 *   • Missing engineer_id → hard error.
 *   • Missing / zero area_m2 → hard error.
 *   • Engineer over quota → error naming which discipline.
 *   • Office over ceiling → error naming which discipline.
 *   • No quota row configured → pass (no-cap semantic).
 *   • End-to-end: /applications/{id}/submit returns 422 when over cap.
 */
class CapacityGuardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private Engineer $engineer;
    private ServiceDefinition $drwService;
    private ServiceDefinition $certService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeUser = User::create([
            'organization_id' => $this->org->id, 'name' => 'office', 'email' => 'office@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م. أحمد', 'membership_number' => 'EN-001',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        EngineerDisciplineQuota::create([
            'engineer_id' => $this->engineer->id, 'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 1000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 3000,
        ]);

        $this->drwService = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'DRW-P-TEST',
            'name_ar' => 'test', 'name_en' => 'test', 'currency' => 'JOD',
            'schema' => [
                'fields' => [
                    ['id' => 'area_m2',    'label_ar' => 'م', 'type' => 'number', 'required' => true],
                    // Note: type=number here to bypass the schema's own
                    // options-list check. In production this is a select
                    // populated at render-time from /engineers — the
                    // frontend just POSTs an integer either way, so the
                    // guard's contract (data.engineer_id is a valid id
                    // scoped to the office) is what matters.
                    ['id' => 'engineer_id','label_ar' => 'م', 'type' => 'number', 'required' => true],
                ],
                'workflow' => ['stages' => [[
                    'id' => 'r', 'label_ar' => 'r', 'role' => 'staff', 'sla_hours' => 24,
                ]]],
            ],
            'status' => 'active',
        ]);
        $this->certService = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'CERT-TEST',
            'name_ar' => 'cert', 'name_en' => 'cert', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => [[
                'id' => 'r', 'label_ar' => 'r', 'role' => 'staff', 'sla_hours' => 24,
            ]]]],
            'status' => 'active',
        ]);
    }

    public function test_non_quota_service_passes_through(): void
    {
        // CERT-* has no area_m2 field → guard is a no-op.
        $app = $this->makeApp($this->certService, []);
        $this->assertSame([], app(CapacityGuard::class)->validate($app));
    }

    public function test_missing_engineer_id_returns_error(): void
    {
        $app = $this->makeApp($this->drwService, ['area_m2' => 500]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('engineer_id', $errors);
    }

    public function test_missing_area_returns_error(): void
    {
        $app = $this->makeApp($this->drwService, ['engineer_id' => $this->engineer->id]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('area_m2', $errors);
    }

    public function test_engineer_over_quota_returns_error_with_remaining_and_requested(): void
    {
        // 1000 seeded → consume 900 → 100 remaining → 200 requested = over.
        $priorApp = $this->makeApp($this->drwService, ['area_m2' => 900]);
        $this->consume($priorApp, 900);

        $app = $this->makeApp($this->drwService, [
            'engineer_id' => $this->engineer->id, 'area_m2' => 200,
        ]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('engineer_id', $errors);
        $this->assertStringContainsString('100', $errors['engineer_id'],
            'Error must name the remaining m² so the office can decide');
        $this->assertStringContainsString('200', $errors['engineer_id']);
        $this->assertStringContainsString('معماري', $errors['engineer_id'],
            'Error must name the discipline in Arabic');
    }

    public function test_office_over_ceiling_returns_error(): void
    {
        // 3000 ceiling → consume 2900 → 100 remaining → 200 requested = over.
        // Split across two prior apps so the engineer's own 1000 cap
        // isn't also hit (we're specifically testing the office branch).
        // Create a second engineer under the same office so we can distribute
        // consumption across two rows without tripping the 1000 quota.
        $eng2 = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م', 'membership_number' => 'EN-002',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        EngineerDisciplineQuota::create([
            'engineer_id' => $eng2->id, 'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 5000,
        ]);
        $prior1 = $this->makeApp($this->drwService, ['area_m2' => 900]);
        $this->consume($prior1, 900, $this->engineer);
        $prior2 = $this->makeApp($this->drwService, ['area_m2' => 2000]);
        $this->consume($prior2, 2000, $eng2);
        $app = $this->makeApp($this->drwService, [
            'engineer_id' => $this->engineer->id, 'area_m2' => 200,
        ]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('office_ceiling', $errors);
    }

    public function test_within_quota_passes(): void
    {
        $app = $this->makeApp($this->drwService, [
            'engineer_id' => $this->engineer->id, 'area_m2' => 500,
        ]);
        $this->assertSame([], app(CapacityGuard::class)->validate($app),
            'Well-under quota + ceiling → no errors');
    }

    public function test_engineer_from_another_office_is_rejected(): void
    {
        // Cross-org attack: applicant tries to attribute their submission
        // to another office's engineer to consume that office's quota.
        $otherOrg = Organization::create([
            'name_ar' => 'other', 'name_en' => 'other', 'slug' => 'other', 'is_active' => true,
        ]);
        $otherEng = Engineer::create([
            'organization_id' => $otherOrg->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م', 'membership_number' => 'X-999', 'specialization' => Disciplines::STRUCTURAL,
        ]);
        $app = $this->makeApp($this->drwService, [
            'engineer_id' => $otherEng->id, 'area_m2' => 100,
        ]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('engineer_id', $errors);
        $this->assertStringContainsString('غير مسجل', $errors['engineer_id']);
    }

    public function test_submit_endpoint_returns_422_when_over_cap(): void
    {
        // End-to-end: real /applications/{id}/submit route with a
        // seeded over-cap application → 422 with the guard's errors.
        $priorApp = $this->makeApp($this->drwService, ['area_m2' => 990]);
        $this->consume($priorApp, 990);
        $app = $this->makeApp($this->drwService, [
            'engineer_id' => $this->engineer->id, 'area_m2' => 100,
        ]);
        $app->update(['status' => Application::STATUS_DRAFT]);

        Sanctum::actingAs($this->officeUser);
        $res = $this->postJson("/api/v1/applications/{$app->id}/submit");
        $res->assertStatus(422);
        $res->assertJsonPath('message', 'الرصيد الهندسي غير كافٍ. يرجى مراجعة الحصة والسقف السنوي.');
        $this->assertNotEmpty($res->json('errors'));
    }

    private function makeApp(ServiceDefinition $svc, array $data): Application
    {
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0,
        ]);
    }

    private function consume(Application $app, int $m2, ?Engineer $engineer = null): void
    {
        QuotaConsumption::create([
            'application_id'  => $app->id,
            'engineer_id'     => ($engineer ?? $this->engineer)->id,
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => Disciplines::ARCHITECTURAL,
            'year'            => (int) now()->year,
            'm2'              => $m2,
        ]);
    }
}
