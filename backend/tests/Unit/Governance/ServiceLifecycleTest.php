<?php

declare(strict_types=1);

namespace Tests\Unit\Governance;

use Illuminate\Support\Carbon;
use Modules\JeaServices\Governance\ServiceLifecycleState;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * SG-01 · lifecycle derivation on ServiceDefinition model.
 *
 * These tests use ServiceDefinition::make() (non-persisted) to exercise the
 * pure derivation logic. A separate feature test covers migration defaults.
 */
class ServiceLifecycleTest extends TestCase
{
    public function test_empty_service_is_draft(): void
    {
        $s = new ServiceDefinition();
        $this->assertSame(ServiceLifecycleState::DRAFT, $s->lifecycle());
    }

    public function test_configured_service_has_top_level_keys(): void
    {
        $s = $this->makeService([
            'schema' => [
                'workflow' => [],
                'fields'   => [],
                'documents' => [],
                'fee'      => [],
            ],
        ]);
        $this->assertSame(ServiceLifecycleState::CONFIGURED, $s->lifecycle());
    }

    public function test_technically_validated_when_stage_and_fee_type_present(): void
    {
        $s = $this->makeService([
            'schema' => [
                'workflow'  => ['stages' => [['id' => 'x', 'role' => 'staff']]],
                'fields'    => [],
                'documents' => [],
                'fee'       => ['type' => 'fixed', 'amount' => 10],
            ],
        ]);
        $this->assertSame(ServiceLifecycleState::TECHNICALLY_VALIDATED, $s->lifecycle());
    }

    public function test_awaiting_uat_when_uat_pending(): void
    {
        $s = $this->makeService([
            'schema'     => $this->validSchema(),
            'uat_status' => 'PENDING',
        ]);
        $this->assertSame(ServiceLifecycleState::AWAITING_UAT, $s->lifecycle());
    }

    public function test_uat_approved_when_all_uat_fields_set(): void
    {
        $s = $this->makeService([
            'schema'         => $this->validSchema(),
            'uat_status'     => 'APPROVED',
            'uat_reference'  => 'JEA-DEC-2026-08-01-42',
            'uat_signed_at'  => Carbon::now(),
        ]);
        $this->assertTrue($s->hasUatApproval());
        $this->assertSame(ServiceLifecycleState::UAT_APPROVED, $s->lifecycle());
    }

    public function test_uat_approved_requires_reference_and_signed_at(): void
    {
        $s = $this->makeService([
            'schema'     => $this->validSchema(),
            'uat_status' => 'APPROVED',
            // no uat_reference, no uat_signed_at
        ]);
        $this->assertFalse($s->hasUatApproval());
        $this->assertSame(ServiceLifecycleState::TECHNICALLY_VALIDATED, $s->lifecycle());
    }

    public function test_published_wins_over_uat_approved(): void
    {
        $s = $this->makeService([
            'schema'             => $this->validSchema(),
            'uat_status'         => 'APPROVED',
            'uat_reference'      => 'ref',
            'uat_signed_at'      => Carbon::now(),
            'publication_status' => 'PUBLISHED',
        ]);
        $this->assertSame(ServiceLifecycleState::PUBLISHED, $s->lifecycle());
    }

    public function test_suspended_wins_over_published(): void
    {
        $s = $this->makeService([
            'schema'             => $this->validSchema(),
            'publication_status' => 'SUSPENDED',
        ]);
        $this->assertSame(ServiceLifecycleState::SUSPENDED, $s->lifecycle());
    }

    public function test_retired_wins_over_all(): void
    {
        $s = $this->makeService([
            'schema'             => $this->validSchema(),
            'uat_status'         => 'APPROVED',
            'uat_reference'      => 'ref',
            'uat_signed_at'      => Carbon::now(),
            'publication_status' => 'RETIRED',
        ]);
        $this->assertSame(ServiceLifecycleState::RETIRED, $s->lifecycle());
    }

    /** @param array<string, mixed> $attributes */
    private function makeService(array $attributes): ServiceDefinition
    {
        $s = new ServiceDefinition();
        // Use forceFill so we don't need casts to be applied via Eloquent.
        $s->forceFill($attributes);
        return $s;
    }

    /** @return array<string, mixed> */
    private function validSchema(): array
    {
        return [
            'workflow'  => ['stages' => [['id' => 'review', 'role' => 'staff']]],
            'fields'    => [],
            'documents' => [],
            'fee'       => ['type' => 'fixed', 'amount' => 25],
        ];
    }
}
