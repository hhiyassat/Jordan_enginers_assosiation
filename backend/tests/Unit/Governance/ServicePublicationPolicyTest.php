<?php

declare(strict_types=1);

namespace Tests\Unit\Governance;

use Illuminate\Support\Carbon;
use Modules\JeaServices\Database\Seeders\ServiceFeeDefaultsSeeder;
use Modules\JeaServices\Governance\PublicationDecision;
use Modules\JeaServices\Governance\ServicePublicationPolicy;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * SG-01 · ServicePublicationPolicy — pure decision, no persistence.
 *
 * Each test isolates one blocker to prove that specific reason code fires;
 * one "happy path" test proves PUB_OK when all conditions are met.
 */
class ServicePublicationPolicyTest extends TestCase
{
    private ServicePublicationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ServicePublicationPolicy();
    }

    public function test_happy_path_returns_pub_ok(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertTrue($decision->allowed, 'expected PUB_OK but got: ' . implode(',', $decision->reasonCodes));
        $this->assertSame([PublicationDecision::PUB_OK], $decision->reasonCodes);
    }

    public function test_placeholder_fee_with_amount_zero_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $schema = $service->schema;
        $schema['fee'] = ['type' => 'fixed', 'amount' => 0];
        $service->schema = $schema;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_PLACEHOLDER_FEE, $decision->reasonCodes);
    }

    public function test_placeholder_fee_from_service_fee_defaults_seeder_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $schema = $service->schema;
        $schema['fee'] = [
            'type' => 'fixed',
            'amount' => ServiceFeeDefaultsSeeder::DEFAULT_AMOUNT_JOD,
            'currency' => 'JOD',
            'source' => 'JORD-85 admin-default — override per service via PATCH /admin/services/{id}/fee once F-07 amounts are published.',
        ];
        $service->schema = $schema;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_PLACEHOLDER_FEE, $decision->reasonCodes);
    }

    public function test_placeholder_workflow_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $schema = $service->schema;
        $schema['workflow']['stages'] = [
            ['id' => 'placeholder_review', 'role' => 'staff'],
        ];
        $service->schema = $schema;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_PLACEHOLDER_WORKFLOW, $decision->reasonCodes);
    }

    public function test_missing_uat_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->uat_status = 'PENDING';

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MISSING_UAT, $decision->reasonCodes);
    }

    public function test_missing_uat_reference_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->uat_reference = null;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MISSING_UAT_REFERENCE, $decision->reasonCodes);
    }

    public function test_missing_publication_reason_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->publication_reason = null;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MISSING_REASON, $decision->reasonCodes);
    }

    public function test_future_effective_from_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->effective_from = Carbon::now()->addDay();

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_EFFECTIVE_FROM_FUTURE, $decision->reasonCodes);
    }

    public function test_past_effective_from_does_not_block(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->effective_from = Carbon::now()->subDay();

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertTrue($decision->allowed);
    }

    public function test_maker_checker_blocks_when_publisher_equals_uat_signer(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->uat_signed_by = 42;

        $decision = $this->policy->evaluate($service, actorUserId: 42);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MAKER_CHECKER, $decision->reasonCodes);
    }

    public function test_maker_checker_allows_when_publisher_differs(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $service->uat_signed_by = 42;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertTrue($decision->allowed);
    }

    public function test_schema_missing_workflow_stages_blocks(): void
    {
        $service = $this->makeServiceReadyForPublication();
        $schema = $service->schema;
        unset($schema['workflow']);
        $service->schema = $schema;

        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_SCHEMA_STRUCTURE, $decision->reasonCodes);
    }

    public function test_multiple_blockers_accumulate(): void
    {
        $service = new ServiceDefinition();
        $service->forceFill([
            'schema' => ['fee' => ['type' => 'fixed', 'amount' => 0]], // missing workflow, fields, documents; placeholder fee
            'uat_status' => 'NOT_SUBMITTED',
            'uat_reference' => null,
            'publication_reason' => null,
        ]);
        $decision = $this->policy->evaluate($service, actorUserId: 999);
        $this->assertFalse($decision->allowed);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MISSING_REASON, $decision->reasonCodes);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_SCHEMA_STRUCTURE, $decision->reasonCodes);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_PLACEHOLDER_FEE, $decision->reasonCodes);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MISSING_UAT, $decision->reasonCodes);
        $this->assertContains(PublicationDecision::PUB_BLOCKED_MISSING_UAT_REFERENCE, $decision->reasonCodes);
    }

    private function makeServiceReadyForPublication(): ServiceDefinition
    {
        $s = new ServiceDefinition();
        $s->forceFill([
            'schema' => [
                'workflow' => [
                    'stages' => [
                        ['id' => 'office_first_auditor', 'role' => 'reviewer', 'sla_hours' => 72],
                        ['id' => 'chairman_sign', 'role' => 'chairman', 'sla_hours' => 24],
                    ],
                ],
                'fields'    => [['id' => 'area_m2', 'type' => 'number', 'required' => true]],
                'documents' => [],
                'fee'       => ['type' => 'per_unit', 'unit' => 'lm', 'rate' => 0.150],
            ],
            'uat_status'         => 'APPROVED',
            'uat_reference'      => 'JEA-DEC-2026-42',
            'uat_signed_at'      => Carbon::now()->subDay(),
            'uat_signed_by'      => 100,
            'publication_reason' => 'Approved by board decision JEA-DEC-2026-42.',
            'effective_from'     => null,
        ]);
        return $s;
    }
}
