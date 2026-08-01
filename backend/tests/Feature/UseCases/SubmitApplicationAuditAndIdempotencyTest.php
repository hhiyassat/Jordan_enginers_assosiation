<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Adapters\Srv001\LegacyBridgeExplorationMatrixRule;
use Modules\JeaServices\Adapters\Srv001\LegacyBridgeNetDepthRule;
use Modules\JeaServices\Adapters\Srv001\LegacyBridgeWellsCountRule;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetNetDepthTableCalculator;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetWellsCountCalculator;
use Modules\JeaServices\Domain\Srv001\TargetSrv001SubmissionPolicy;
use Modules\JeaServices\Governance\ApplicationVersionBinder;
use Modules\JeaServices\Governance\CalculationSnapshotWriter;
use Modules\JeaServices\Governance\ServiceVersionPublisher;
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;
use Modules\JeaServices\Governance\SubmissionAuditRecorder;
use Modules\JeaServices\Governance\SubmissionAuditRecorderContract;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\CalculationSnapshot;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\UseCases\SubmitApplication\SubmitApplicationUseCase;
use Tests\TestCase;

/**
 * TD-02-SUPP · audit-inside-transaction + audit-failure rollback + honest
 * idempotency reconciliation.
 *
 * Complements SubmitApplicationUseCaseTest with the mandate-required
 * audit persistence proof and the duplicate-attempt behaviour proof.
 *
 * IDEMPOTENCY_CONTRACT_STATUS=ABSENT — no application-submission
 * idempotency contract exists in the codebase today (verified by
 * repository-wide grep: payment_callbacks + queue jobs have their own
 * mechanisms; no idempotency-key middleware for application submission).
 * Duplicate-attempt behaviour therefore relies on the SG-04
 * unique-constraint on (application_id, rule_version_id, purpose='SUBMIT');
 * proven below.
 */
class SubmitApplicationAuditAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $publisher;
    private ServiceDefinition $service;
    private TargetSrv001SubmissionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id,
            'name' => 'app', 'email' => 'app-supp@t.test', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->publisher = User::create([
            'organization_id' => $this->org->id,
            'name' => 'pub', 'email' => 'pub-supp@t.test', 'password' => 'x',
            'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'SRV-001', 'name_ar' => 'x', 'name_en' => 'x', 'currency' => 'JOD',
            'schema' => [
                'workflow' => [
                    'stages'  => [['id' => 'r', 'role' => 'staff', 'sla_hours' => 24]],
                    'routing' => [[
                        'action' => 'ROUTE_TO_SERVICE', 'target' => 'SRV-006',
                        'when' => ['project_sector' => 'حكومي'],
                        'message_ar' => 'المشاريع الحكومية تُقدَّم عبر خدمة SRV-006.',
                    ]],
                ],
                'fields' => [], 'documents' => [],
                'fee' => ['type' => 'per_unit', 'unit' => 'lm', 'rate' => 0.150],
            ],
            'status' => 'active',
        ]);
        (new Srv001RulesSeeder())->run();

        $this->policy = new TargetSrv001SubmissionPolicy(
            new TargetExplorationRequirementMatrixCalculator(
                new LegacyBridgeExplorationMatrixRule(new LegacyExplorationRequirementMatrixCalculator()),
            ),
            new TargetWellsCountCalculator(
                new LegacyBridgeWellsCountRule(new LegacyWellsCountCalculator()),
            ),
            new TargetNetDepthTableCalculator(
                new LegacyBridgeNetDepthRule(new LegacyNetDepthTableCalculator()),
            ),
        );
    }

    // ── Audit persistence inside the transaction ──────────────────────

    public function test_committed_result_writes_audit_event_inside_the_same_transaction(): void
    {
        $version = (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-supp-1', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $useCase = $this->makeUseCase(new SubmissionAuditRecorder());
        $app     = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);

        $auditCountBefore = AuditLog::count();
        $result = $useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        $this->assertTrue($result->succeeded);
        $this->assertNotNull($result->auditEventId, 'result must expose auditEventId when committed');
        $this->assertSame($auditCountBefore + 1, AuditLog::count(), 'exactly one audit row written');

        $audit = AuditLog::query()->findOrFail($result->auditEventId);
        $this->assertSame(SubmissionAuditRecorder::ACTION, $audit->action);
        $this->assertSame($this->applicant->id, $audit->user_id);
        $this->assertSame($app->getMorphClass(), $audit->auditable_type);
        $this->assertSame($app->id, $audit->auditable_id);
        // The audit extra must reference the version + snapshot ids + rule identifiers
        $extra = $audit->extra;
        $this->assertSame($version->id, $extra['service_definition_version_id'] ?? null);
        $this->assertSame('BOUND', $extra['version_binding_classification'] ?? null);
        $this->assertCount(3, $extra['snapshot_ids'] ?? []);
        $this->assertContains('SRV001_EXPLORATION_MATRIX', $extra['rule_identifiers'] ?? []);
        $this->assertTrue($extra['target_domain_provisional'] ?? false);
    }

    // ── Audit failure rolls back application + binding + provenance + snapshots ──

    public function test_audit_persistence_failure_rolls_back_application(): void
    {
        $version = (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-supp-2', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $useCase = $this->makeUseCase($this->throwingRecorder('simulated audit persistence failure'));
        $app     = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $originalData      = $app->data;
        $originalVersionId = $app->service_definition_version_id;
        $originalAuditRows = AuditLog::count();

        $result = $useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        // Result reports rollback
        $this->assertFalse($result->succeeded);
        $this->assertStringContainsString('simulated audit persistence failure', $result->rollbackReason ?? '');
        $this->assertNull($result->auditEventId);

        // Application state fully reverted — PARTIAL_PERSISTENCE=0
        $fresh = $app->fresh();
        $this->assertSame($originalData, $fresh->data, 'application.data reverted');
        $this->assertSame($originalVersionId, $fresh->service_definition_version_id, 'version binding reverted');
        $this->assertSame(0, CalculationSnapshot::where('application_id', $app->id)->count(), 'no snapshots persisted');
        $this->assertSame($originalAuditRows, AuditLog::count(), 'no audit rows persisted');
    }

    public function test_audit_failure_rolls_back_version_binding_specifically(): void
    {
        // Publish a version so binding *would* succeed. Audit throws after
        // binding + snapshots complete — rollback must un-bind.
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-supp-3', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $useCase = $this->makeUseCase($this->throwingRecorder('audit-rollback'));
        $app     = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);

        $useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        $this->assertNull($app->fresh()->service_definition_version_id,
            'binding rolled back even though the binder itself succeeded');
    }

    public function test_audit_failure_rolls_back_snapshots_and_provenance_specifically(): void
    {
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-supp-4', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $useCase = $this->makeUseCase($this->throwingRecorder('audit-rollback'));
        $app     = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);

        $useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        // The three snapshots + their rule_version_id provenance links
        // are all rolled back — zero rows remain.
        $this->assertSame(0, CalculationSnapshot::where('application_id', $app->id)->count());
    }

    // ── Rejected / blocked decisions write no audit and no application state ──

    public function test_rejected_decision_writes_no_audit_and_no_application_state(): void
    {
        $useCase = $this->makeUseCase(new SubmissionAuditRecorder());
        $app     = $this->makeApp([
            'project_sector' => 'حكومي', // routes to SRV-006 → rejected
            'floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2,
        ]);
        $originalAuditCount = AuditLog::count();

        $result = $useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        $this->assertFalse($result->succeeded);
        $this->assertNull($result->auditEventId);
        $this->assertSame($originalAuditCount, AuditLog::count(), 'no audit event on rejected decision');
        $this->assertSame(0, CalculationSnapshot::where('application_id', $app->id)->count());
    }

    // ── Idempotency (no contract exists — duplicate-attempt behaviour only) ──

    public function test_idempotency_contract_status_is_absent_and_documented_here(): void
    {
        // Structural assertion — no idempotency-key middleware, no
        // IdempotencyKey table, no request-scoped idempotency guard for
        // application submission. Documenting via this test so future
        // audits find the residual (see residual register RES-TD02-SUPP-01).
        $this->assertFalse(
            class_exists('App\Http\Middleware\IdempotencyKey'),
            'documents current state — no idempotency middleware exists',
        );
        $this->assertFalse(
            class_exists('App\Models\IdempotencyKey'),
            'documents current state — no idempotency table exists',
        );
        // Adjacent contracts (payment callbacks) confirmed elsewhere in
        // the repo (PaymentCallbackController); this is scoped strictly
        // to application submission.
        $this->addToAssertionCount(1);
    }

    public function test_duplicate_attempt_atomic_rollback_via_snapshot_unique_constraint(): void
    {
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-supp-5', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $useCase = $this->makeUseCase(new SubmissionAuditRecorder());
        $app     = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $decision = $this->policy->evaluate($app);

        $first = $useCase->execute($app, $decision, $this->applicant);
        $this->assertTrue($first->succeeded);
        $snapshotCountAfterFirst = CalculationSnapshot::where('application_id', $app->id)->count();
        $auditCountAfterFirst    = AuditLog::count();

        // Second attempt (simulating a network retry today — WITHOUT an
        // idempotency contract). Expected: SG-04 unique constraint
        // rejects the SUBMIT snapshot; whole transaction rolls back;
        // no additional snapshot AND no additional audit row.
        $second = $useCase->execute($app->fresh(), $decision, $this->applicant);
        $this->assertFalse($second->succeeded);
        $this->assertMatchesRegularExpression('/QueryException|UniqueConstraintViolationException/', $second->rollbackReason ?? '');

        $this->assertSame($snapshotCountAfterFirst, CalculationSnapshot::where('application_id', $app->id)->count(),
            'no additional snapshots after duplicate attempt');
        $this->assertSame($auditCountAfterFirst, AuditLog::count(),
            'no additional audit rows after duplicate attempt (audit is inside the rolled-back tx)');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function makeUseCase(SubmissionAuditRecorderContract $recorder): SubmitApplicationUseCase
    {
        return new SubmitApplicationUseCase(
            versionBinder:  new ApplicationVersionBinder(new ServiceVersionPublisher()),
            snapshotWriter: new CalculationSnapshotWriter(),
            auditRecorder:  $recorder,
        );
    }

    private function throwingRecorder(string $message): SubmissionAuditRecorderContract
    {
        return new class($message) implements SubmissionAuditRecorderContract {
            public function __construct(private readonly string $msg) {}
            public function recordSubmissionCommitted(User $actor, Application $application, array $extra): int
            {
                throw new \RuntimeException($this->msg);
            }
        };
    }

    /** @param array<string, mixed> $data */
    private function makeApp(array $data): Application
    {
        return Application::create([
            'reference_number'      => 'ref-supp-' . uniqid(),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0,
            'payment_status'        => 'pending',
        ]);
    }
}
