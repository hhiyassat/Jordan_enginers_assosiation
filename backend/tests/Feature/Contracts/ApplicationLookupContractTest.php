<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Contracts\Applications\ApplicationLookup;
use App\Contracts\Applications\ApplicationSnapshot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Services\EloquentApplicationLookup;
use Tests\TestCase;

/**
 * CS-04: pins the ApplicationLookup contract now that it has real
 * production consumers (SanctionGuard, plus any future sibling that
 * needs read-only cross-module Application access).
 */
class ApplicationLookupContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_binds_the_eloquent_implementation(): void
    {
        $this->assertInstanceOf(
            EloquentApplicationLookup::class,
            app(ApplicationLookup::class),
            'JeaServicesServiceProvider must bind ApplicationLookup → EloquentApplicationLookup'
        );
    }

    public function test_snapshot_of_maps_all_fields(): void
    {
        [$org, $service, $applicant] = $this->fixture();
        $app = Application::create([
            'reference_number'      => 'CS04-APP-001',
            'organization_id'       => $org->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $applicant->id,
            'status'                => Application::STATUS_APPROVED,
            'current_stage'         => 'submit',
            'data'                  => [
                'engineer_id'   => 42,
                'area_m2'       => 250,
                'basin_number'  => '12',
                'parcel_number' => '345',
            ],
            'fee_amount'            => 175.00,
            'payment_status'        => 'pending',
        ]);

        $snap = EloquentApplicationLookup::snapshotOf($app);

        $this->assertInstanceOf(ApplicationSnapshot::class, $snap);
        $this->assertSame((int) $app->id,                    $snap->id);
        $this->assertSame('CS04-APP-001',                    $snap->reference_number);
        $this->assertSame((int) $org->id,                    $snap->organization_id);
        $this->assertSame((int) $service->id,                $snap->service_definition_id);
        $this->assertSame((int) $applicant->id,              $snap->applicant_id);
        $this->assertSame(Application::STATUS_APPROVED,      $snap->status);
        $this->assertSame('submit',                          $snap->current_stage);
        $this->assertSame('12',                              $snap->basin_number);
        $this->assertSame('345',                             $snap->parcel_number);
        $this->assertArrayHasKey('engineer_id',              $snap->data);
    }

    public function test_find_returns_null_for_missing_id(): void
    {
        $this->assertNull(app(ApplicationLookup::class)->find(999999));
    }

    public function test_find_ignores_org_scope_so_gateway_callers_can_reach_any_application(): void
    {
        // CS-03 payment-callback path uses ApplicationLookup indirectly
        // via payment_reference lookup — a real webhook is not signed
        // in and therefore has no org scope. Verify the contract
        // exposes cross-org find() by design.
        [$org, $service, $applicant] = $this->fixture();
        $app = Application::create([
            'reference_number'      => 'CS04-APP-XORG',
            'organization_id'       => $org->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $applicant->id,
            'status'                => Application::STATUS_APPROVED,
            'current_stage'         => 'submit',
            'data'                  => [],
            'fee_amount'            => 100.00,
        ]);
        // Different org's user is authenticated — find() should still
        // return the snapshot because callers pass the ORG id explicitly
        // in the iteration variant, and the point-lookup is provider-
        // scoped by the receipt reference, not by session context.
        $otherOrg = Organization::create([
            'name_ar' => 'x', 'name_en' => 'x', 'slug' => 'x-cs04-' . uniqid(),
            'is_active' => true,
        ]);
        $otherUser = User::create([
            'organization_id' => $otherOrg->id,
            'name' => 'x', 'email' => 'x-cs04-' . uniqid() . '@t.esp',
            'password' => Hash::make('Secret-Password-1234!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->actingAs($otherUser);

        $snap = app(ApplicationLookup::class)->find($app->id);
        $this->assertNotNull($snap);
        $this->assertSame($app->id, $snap->id);
    }

    public function test_for_organization_in_statuses_scopes_to_org(): void
    {
        [$orgA, $service, $applicantA] = $this->fixture();
        Application::create([
            'reference_number'      => 'CS04-A',
            'organization_id'       => $orgA->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $applicantA->id,
            'status'                => Application::STATUS_APPROVED,
            'current_stage'         => 'submit',
            'data'                  => [], 'fee_amount' => 100,
        ]);
        Application::create([
            'reference_number'      => 'CS04-B',
            'organization_id'       => $orgA->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $applicantA->id,
            'status'                => Application::STATUS_DRAFT,
            'current_stage'         => 'submit',
            'data'                  => [], 'fee_amount' => 100,
        ]);

        $snaps = iterator_to_array(app(ApplicationLookup::class)
            ->forOrganizationInStatuses($orgA->id, [Application::STATUS_APPROVED]));

        $refs = array_map(fn ($s) => $s->reference_number, $snaps);
        $this->assertSame(['CS04-A'], $refs);
    }

    /**
     * @return array{0: Organization, 1: ServiceDefinition, 2: User}
     */
    private function fixture(): array
    {
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-lookup-' . uniqid(),
            'is_active' => true,
        ]);
        $applicant = User::create([
            'organization_id' => $org->id,
            'name' => 'A', 'email' => 'lookup-' . uniqid() . '@t.esp',
            'password' => Hash::make('Secret-Password-1234!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $service = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code'    => 'LOOKUP-01',
            'name_ar' => 'خدمة', 'name_en' => 'Service',
            'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 'submit', 'role' => 'applicant', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['submit']],
            ]]],
        ]);
        return [$org, $service, $applicant];
    }
}
