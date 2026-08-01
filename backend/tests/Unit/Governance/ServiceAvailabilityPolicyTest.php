<?php

declare(strict_types=1);

namespace Tests\Unit\Governance;

use Illuminate\Support\Carbon;
use Modules\JeaServices\Governance\ServiceAvailabilityPolicy;
use Modules\JeaServices\Governance\ServiceAvailabilityVerdict;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * SG-02 · ServiceAvailabilityPolicy — verdict per lifecycle state.
 *
 * Verifies the preference order documented in
 * docs/architecture/service-governance/judgments/JDG-SG02-01-availability-preference-order.md.
 */
class ServiceAvailabilityPolicyTest extends TestCase
{
    private ServiceAvailabilityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ServiceAvailabilityPolicy();
    }

    public function test_retired_hidden_for_applicant_visible_for_admin(): void
    {
        $s = $this->makeService(['publication_status' => 'RETIRED']);

        $applicantVerdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertFalse($applicantVerdict->catalogVisible);
        $this->assertFalse($applicantVerdict->applicationCreationAllowed);
        $this->assertFalse($applicantVerdict->submissionAllowed);
        $this->assertTrue($applicantVerdict->certificateAllowed, 'historical certs still issuable');
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_HIDDEN_RETIRED, $applicantVerdict->reasonCodes);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_ALLOWED_HISTORICAL_ONLY, $applicantVerdict->reasonCodes);

        $adminVerdict = $this->policy->evaluate($s, actorIsAdmin: true);
        $this->assertTrue($adminVerdict->catalogVisible);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_ALLOWED_ADMIN_INSPECTION, $adminVerdict->reasonCodes);
    }

    public function test_suspended_hidden_for_applicant_visible_for_admin(): void
    {
        $s = $this->makeService(['publication_status' => 'SUSPENDED']);

        $applicantVerdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertFalse($applicantVerdict->catalogVisible);
        $this->assertFalse($applicantVerdict->applicationCreationAllowed);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_HIDDEN_SUSPENDED_FOR_APPLICANT, $applicantVerdict->reasonCodes);

        $adminVerdict = $this->policy->evaluate($s, actorIsAdmin: true);
        $this->assertTrue($adminVerdict->catalogVisible);
    }

    public function test_published_service_is_available_for_all(): void
    {
        $s = $this->makePublishedService();
        $verdict = $this->policy->evaluate($s, actorIsAdmin: false);

        $this->assertTrue($verdict->catalogVisible);
        $this->assertTrue($verdict->applicationCreationAllowed);
        $this->assertTrue($verdict->submissionAllowed);
        $this->assertTrue($verdict->paymentAllowed);
        $this->assertTrue($verdict->certificateAllowed);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_OK, $verdict->reasonCodes);
    }

    public function test_published_service_with_placeholder_fee_blocks_operations(): void
    {
        $s = $this->makePublishedService();
        $schema = $s->schema;
        $schema['fee'] = ['type' => 'fixed', 'amount' => 0];
        $s->schema = $schema;

        $verdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertFalse($verdict->catalogVisible);
        $this->assertFalse($verdict->applicationCreationAllowed);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_BLOCKED_PLACEHOLDER_FEE, $verdict->reasonCodes);
    }

    public function test_published_service_with_placeholder_workflow_blocks_operations(): void
    {
        $s = $this->makePublishedService();
        $schema = $s->schema;
        $schema['workflow']['stages'] = [['id' => 'placeholder_review', 'role' => 'staff']];
        $s->schema = $schema;

        $verdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertFalse($verdict->catalogVisible);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_BLOCKED_PLACEHOLDER_WORKFLOW, $verdict->reasonCodes);
    }

    public function test_published_service_with_future_effective_from_blocks_operations(): void
    {
        $s = $this->makePublishedService();
        $s->effective_from = Carbon::now()->addDay();

        $verdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertFalse($verdict->catalogVisible);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_BLOCKED_EFFECTIVE_FROM_FUTURE, $verdict->reasonCodes);
    }

    public function test_legacy_active_service_visible_with_fallback_warning_under_lenient_mode(): void
    {
        $s = $this->makeService([
            'status'             => 'active',
            'publication_status' => 'NOT_PUBLISHED',
            'schema'             => $this->validSchema(),
        ]);

        $verdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertTrue($verdict->catalogVisible);
        $this->assertTrue($verdict->applicationCreationAllowed);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_LEGACY_STATUS_FALLBACK, $verdict->reasonCodes);
    }

    public function test_legacy_inactive_service_hidden_from_applicant_visible_to_admin(): void
    {
        $s = $this->makeService([
            'status'             => 'inactive',
            'publication_status' => 'NOT_PUBLISHED',
            'schema'             => $this->validSchema(),
        ]);

        $applicantVerdict = $this->policy->evaluate($s, actorIsAdmin: false);
        $this->assertFalse($applicantVerdict->catalogVisible);
        $this->assertFalse($applicantVerdict->applicationCreationAllowed);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_HIDDEN_NOT_PUBLISHED, $applicantVerdict->reasonCodes);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_BLOCKED_LEGACY_STATUS_INACTIVE, $applicantVerdict->reasonCodes);

        $adminVerdict = $this->policy->evaluate($s, actorIsAdmin: true);
        $this->assertTrue($adminVerdict->catalogVisible);
    }

    public function test_strict_mode_disables_legacy_fallback(): void
    {
        $s = $this->makeService([
            'status'             => 'active',
            'publication_status' => 'NOT_PUBLISHED',
            'schema'             => $this->validSchema(),
        ]);

        $strictPolicy = new ServiceAvailabilityPolicy(ServiceAvailabilityPolicy::MODE_STRICT);
        $verdict = $strictPolicy->evaluate($s, actorIsAdmin: false);

        $this->assertFalse($verdict->catalogVisible);
        $this->assertContains(ServiceAvailabilityVerdict::AVAIL_HIDDEN_NOT_PUBLISHED, $verdict->reasonCodes);
    }

    public function test_service_code_and_evaluated_at_populated(): void
    {
        $s = $this->makePublishedService();
        $verdict = $this->policy->evaluate($s);

        $this->assertSame('TEST-100', $verdict->serviceCode);
        $this->assertGreaterThan(0, $verdict->evaluatedAt->getTimestamp());
    }

    /** @param array<string, mixed> $attributes */
    private function makeService(array $attributes): ServiceDefinition
    {
        $s = new ServiceDefinition();
        $attributes['code'] ??= 'TEST-100';
        $s->forceFill($attributes);
        return $s;
    }

    private function makePublishedService(): ServiceDefinition
    {
        return $this->makeService([
            'schema'             => $this->validSchema(),
            'status'             => 'active',
            'publication_status' => 'PUBLISHED',
        ]);
    }

    /** @return array<string, mixed> */
    private function validSchema(): array
    {
        return [
            'workflow'  => ['stages' => [['id' => 'review', 'role' => 'staff', 'sla_hours' => 24]]],
            'fields'    => [],
            'documents' => [],
            'fee'       => ['type' => 'per_unit', 'unit' => 'lm', 'rate' => 0.150],
        ];
    }
}
