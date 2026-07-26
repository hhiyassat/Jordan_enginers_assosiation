<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Engine\ServiceSubmissionGuard;
use Modules\JeaServices\Engine\ServiceSubmissionGuardRegistry;
use Modules\JeaServices\Engine\Srv001Guard;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * Pins the generic submission-guard registry:
 *   • SRV-001 resolves to Srv001Guard and its validate() runs.
 *   • Unrelated services resolve to no guard and get a pass-through.
 *   • The ApplicationController never mentions any service code —
 *     guarded by a static file assertion.
 */
class ServiceSubmissionGuardRegistryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id,
            'name'  => 'office',
            'email' => 'registry@t.esp',
            'password' => 'x',
            'role' => 'applicant',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    public function test_registered_guard_is_invoked_for_matching_service_code(): void
    {
        $spy = new class implements ServiceSubmissionGuard {
            public int $callCount = 0;
            public function validate(Application $app): array
            {
                $this->callCount++;
                return ['x' => 'blocked'];
            }
        };
        $registry = new ServiceSubmissionGuardRegistry(['SRV-TEST' => $spy]);

        $svc = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'SRV-TEST',
            'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD', 'schema' => ['fields' => []],
        ]);
        $app = Application::create([
            'reference_number'      => Application::generateReference($svc),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        $errors = $registry->validate($app);
        $this->assertSame(1, $spy->callCount);
        $this->assertSame(['x' => 'blocked'], $errors);
    }

    public function test_unregistered_service_code_yields_pass_through(): void
    {
        $spy = new class implements ServiceSubmissionGuard {
            public int $callCount = 0;
            public function validate(Application $app): array { $this->callCount++; return []; }
        };
        $registry = new ServiceSubmissionGuardRegistry(['SRV-OTHER' => $spy]);

        $svc = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-P-999',
            'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD', 'schema' => ['fields' => []],
        ]);
        $app = Application::create([
            'reference_number'      => Application::generateReference($svc),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        $this->assertSame([], $registry->validate($app));
        $this->assertSame(0, $spy->callCount,
            'guard for SRV-OTHER must not be invoked for DRW-P-999');
    }

    public function test_service_provider_wires_srv001_guard_by_default(): void
    {
        // The real container-resolved registry (bound in
        // JeaServicesServiceProvider) must know about SRV-001.
        $registry = app(ServiceSubmissionGuardRegistry::class);
        $this->assertContains(
            Srv001Guard::SERVICE_CODE,
            $registry->registeredCodes(),
            'JeaServicesServiceProvider must register Srv001Guard for SRV-001',
        );
    }

    public function test_application_controller_has_no_service_code_branching(): void
    {
        // Static guard against re-introducing service-code branching in
        // the generic controller. If a new special-case ever leaks in,
        // this test surfaces it before the branch grows.
        $src = file_get_contents(
            base_path('modules/JeaServices/Http/Controllers/ApplicationController.php'),
        );
        $this->assertIsString($src);
        $this->assertStringNotContainsString('SRV-001', $src,
            'ApplicationController must not mention SRV-001 directly');
        $this->assertStringNotContainsString('Srv001Guard', $src,
            'ApplicationController must not reference Srv001Guard — resolve via registry');
        $this->assertStringNotContainsString(
            "'DRW-P-",
            $src,
            'ApplicationController must not branch on drawing service codes',
        );
    }
}
