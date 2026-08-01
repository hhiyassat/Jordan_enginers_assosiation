<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases;

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
use Modules\JeaServices\Governance\ApplicationVersionBinderContract;
use Modules\JeaServices\Governance\CalculationSnapshotWriter;
use Modules\JeaServices\Governance\ServiceSubmissionDecision;
use Modules\JeaServices\Governance\ServiceVersionPublisher;
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\CalculationSnapshot;
use Modules\JeaServices\Models\RuleVersion;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\UseCases\SubmitApplication\SubmitApplicationResult;
use Modules\JeaServices\UseCases\SubmitApplication\SubmitApplicationUseCase;
use Tests\TestCase;

/**
 * TD-02 · SubmitApplicationUseCase — proves atomicity, rollback
 * semantics, snapshot immutability, rule provenance, and that legacy
 * numeric outputs remain unchanged.
 *
 * The use case is service-code-agnostic; SRV-001 is used here only as a
 * convenient decision producer (TargetSrv001SubmissionPolicy delegating
 * to LegacyBridge* adapters — numeric outputs identical to Srv001Guard).
 */
class SubmitApplicationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $publisher;
    private ServiceDefinition $service;
    private SubmitApplicationUseCase $useCase;
    private TargetSrv001SubmissionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id,
            'name' => 'app', 'email' => 'app@t.test', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->publisher = User::create([
            'organization_id' => $this->org->id,
            'name' => 'pub', 'email' => 'pub@t.test', 'password' => 'x',
            'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);

        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'SRV-001', 'name_ar' => 'x', 'name_en' => 'x', 'currency' => 'JOD',
            'schema' => [
                'workflow' => [
                    'stages' => [['id' => 'r', 'role' => 'staff', 'sla_hours' => 24]],
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

        $this->useCase = new SubmitApplicationUseCase(
            versionBinder:   new ApplicationVersionBinder(new ServiceVersionPublisher()),
            snapshotWriter:  new CalculationSnapshotWriter(),
            auditRecorder:   new \Modules\JeaServices\Governance\SubmissionAuditRecorder(),
        );
    }

    // ── Happy path — atomic commit ────────────────────────────────────

    public function test_committed_result_persists_data_binds_version_writes_snapshots_all_together(): void
    {
        $version = (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-1', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $decision = $this->policy->evaluate($app);
        $this->assertTrue($decision->accepted);

        $result = $this->useCase->execute($app, $decision, $this->applicant);

        // Result reports success
        $this->assertInstanceOf(SubmitApplicationResult::class, $result);
        $this->assertTrue($result->succeeded);
        $this->assertSame('BOUND', $result->versionBindingClassification);
        $this->assertSame($version->id, $result->boundVersionId);
        $this->assertCount(3, $result->snapshotIds, 'one snapshot per calculator');
        $this->assertTrue($result->derivedValuesPersisted);

        // DB reflects all three side effects
        $fresh = $app->fresh();
        $this->assertSame($version->id, $fresh->service_definition_version_id, 'version bound');
        $this->assertSame('CALCULATED', $fresh->data['exploration_requirement_status'], 'derived values persisted');
        $this->assertSame(2, $fresh->data['minimum_exploration_point_count']);
        $this->assertSame(3, CalculationSnapshot::where('application_id', $app->id)
            ->where('purpose', CalculationSnapshot::PURPOSE_SUBMIT)->count(), 'three SUBMIT snapshots');
    }

    // ── Rejected decision → no DB writes ──────────────────────────────

    public function test_rejected_decision_returns_early_without_touching_db(): void
    {
        $app = $this->makeApp([
            'project_sector' => 'حكومي', // routes to SRV-006 → rejected
            'floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2,
        ]);
        $decision = $this->policy->evaluate($app);
        $this->assertFalse($decision->accepted);

        $originalData = $app->data;
        $originalVersionId = $app->service_definition_version_id;

        $result = $this->useCase->execute($app, $decision, $this->applicant);

        $this->assertFalse($result->succeeded);
        $this->assertArrayHasKey('project_sector', $result->rejectionErrors);
        $this->assertSame('NOT_ATTEMPTED', $result->versionBindingClassification);
        $this->assertSame([], $result->snapshotIds);

        // DB unchanged
        $fresh = $app->fresh();
        $this->assertSame($originalData, $fresh->data);
        $this->assertSame($originalVersionId, $fresh->service_definition_version_id);
        $this->assertSame(0, CalculationSnapshot::where('application_id', $app->id)->count());
    }

    // ── SUBMISSION_TRANSACTION_ATOMIC — snapshot failure rolls back application ──

    public function test_snapshot_failure_rolls_back_application_data_and_version_binding(): void
    {
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-2', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $decision = $this->policy->evaluate($app);

        $originalData = $app->data;
        $this->assertNull($app->service_definition_version_id);

        // Pre-insert a SUBMIT snapshot for ONE of the rule versions the
        // decision will write — that triggers the unique-constraint
        // violation on (application_id, rule_version_id, purpose='SUBMIT')
        // during the second write inside the transaction.
        $conflictingRuleVersionId = $decision->calculationSnapshots[0]['rule_version_id'];
        $writer = new CalculationSnapshotWriter();
        $writer->writeForSubmit(
            $app,
            RuleVersion::query()->findOrFail($conflictingRuleVersionId),
            ['pre' => 'existing'], ['pre' => 'existing'],
        );

        $result = $this->useCase->execute($app, $decision, $this->applicant);

        // Result reports rollback
        $this->assertFalse($result->succeeded);
        $this->assertNotNull($result->rollbackReason);
        $this->assertMatchesRegularExpression('/QueryException|UniqueConstraintViolationException/', $result->rollbackReason);

        // Application state UNCHANGED — the transaction rolled back the
        // data merge AND the version bind AND the partial snapshot writes.
        $fresh = $app->fresh();
        $this->assertSame($originalData, $fresh->data, 'application.data rolled back');
        $this->assertNull($fresh->service_definition_version_id, 'version bind rolled back');
        // The pre-existing snapshot (inserted outside the tx) survives; no
        // additional snapshot rows created by the failed use case call.
        $this->assertSame(1, CalculationSnapshot::where('application_id', $app->id)
            ->where('purpose', CalculationSnapshot::PURPOSE_SUBMIT)->count());
    }

    // ── VERSION_BINDING_FAILURE_ROLLS_BACK_APPLICATION ────────────────

    public function test_version_binding_failure_rolls_back_application(): void
    {
        // Simulate a version-binder that throws to prove rollback semantics
        // reach the version-binding step as well as the snapshot step.
        // Uses the ApplicationVersionBinderContract interface (TD-02) so
        // we can substitute without subclassing the final class.
        $throwingBinder = new class implements ApplicationVersionBinderContract {
            public function bindOrClassifyLegacy(Application $app): string
            {
                throw new \RuntimeException('simulated version-binding failure');
            }
        };
        $useCaseWithFailure = new SubmitApplicationUseCase(
            versionBinder:  $throwingBinder,
            snapshotWriter: new CalculationSnapshotWriter(),
            auditRecorder:  new \Modules\JeaServices\Governance\SubmissionAuditRecorder(),
        );

        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $decision = $this->policy->evaluate($app);
        $originalData = $app->data;

        $result = $useCaseWithFailure->execute($app, $decision, $this->applicant);

        $this->assertFalse($result->succeeded);
        $this->assertStringContainsString('simulated version-binding failure', $result->rollbackReason ?? '');

        // Application state UNCHANGED
        $fresh = $app->fresh();
        $this->assertSame($originalData, $fresh->data);
        $this->assertNull($fresh->service_definition_version_id);
        $this->assertSame(0, CalculationSnapshot::where('application_id', $app->id)->count());
    }

    // ── CALCULATION_SNAPSHOT_IMMUTABLE ────────────────────────────────

    public function test_committed_snapshots_are_immutable(): void
    {
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-3', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $result = $this->useCase->execute($app, $this->policy->evaluate($app), $this->applicant);
        $this->assertTrue($result->succeeded);

        $snap = CalculationSnapshot::query()->findOrFail($result->snapshotIds[0]);
        $snap->outputs = ['tampered' => true];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Immutable after insert/');
        $snap->save();
    }

    // ── RULE_PROVENANCE_RECORDED ──────────────────────────────────────

    public function test_snapshots_reference_the_three_srv001_rule_versions(): void
    {
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-4', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $result = $this->useCase->execute($app, $this->policy->evaluate($app), $this->applicant);
        $this->assertTrue($result->succeeded);

        $ruleIdentifiers = [];
        foreach ($result->snapshotIds as $snapId) {
            $snap = CalculationSnapshot::query()->findOrFail($snapId);
            $rv = RuleVersion::query()->findOrFail($snap->rule_version_id);
            $ruleIdentifiers[] = $rv->ruleDefinition->rule_identifier;
        }
        sort($ruleIdentifiers);
        $this->assertSame([
            'SRV001_EXPLORATION_MATRIX',
            'SRV001_NET_DEPTH',
            'SRV001_WELLS_COUNT',
        ], $ruleIdentifiers);
    }

    // ── LEGACY_NUMERIC_OUTPUTS_UNCHANGED ──────────────────────────────

    public function test_use_case_does_not_alter_legacy_calculator_outputs(): void
    {
        // Run the legacy calculator directly.
        $legacyMatrix = new LegacyExplorationRequirementMatrixCalculator();
        $legacyOut    = $legacyMatrix->compute(['floor_count' => 3, 'floor_area' => 500])->outputs;

        // Then invoke the use case with a decision built by the target
        // policy (which delegates to the same legacy engine via the bridge).
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-5', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 500, 'actual_exploration_point_count' => 3, 'project_sector' => 'خاص']);
        $decision = $this->policy->evaluate($app);
        $result   = $this->useCase->execute($app, $decision, $this->applicant);
        $this->assertTrue($result->succeeded);

        // The snapshot's outputs must equal the legacy compute output.
        $matrixSnapshot = null;
        foreach ($result->snapshotIds as $sid) {
            $s = CalculationSnapshot::query()->findOrFail($sid);
            if ($s->ruleVersion->ruleDefinition->rule_identifier === 'SRV001_EXPLORATION_MATRIX') {
                $matrixSnapshot = $s;
                break;
            }
        }
        $this->assertNotNull($matrixSnapshot);
        $this->assertSame($legacyOut, $matrixSnapshot->outputs, 'snapshot must record legacy-identical outputs');
    }

    // ── TARGET_RUNTIME_ACTIVATED=NO ───────────────────────────────────

    public function test_srv001guard_remains_the_only_wired_srv001_runtime(): void
    {
        // Source-level assertion: no controller (Application / Payments /
        // Certificates / Workflow) imports SubmitApplicationUseCase yet.
        $controllerFiles = [
            'modules/JeaServices/Http/Controllers/ApplicationController.php',
            'modules/JeaServices/Http/Controllers/PaymentsController.php',
            'modules/JeaServices/Http/Controllers/CertificatesController.php',
            'modules/JeaServices/Engine/WorkflowEngine.php',
        ];
        foreach ($controllerFiles as $rel) {
            $src = file_get_contents(base_path($rel));
            $this->assertNotFalse($src, "unable to read {$rel}");
            $this->assertStringNotContainsString(
                'SubmitApplicationUseCase',
                $src,
                "{$rel} must not wire SubmitApplicationUseCase yet (TARGET_RUNTIME_ACTIVATED=NO invariant).",
            );
        }
    }

    public function test_srv001guard_still_registered_in_the_submission_guard_registry(): void
    {
        // ServiceSubmissionGuardRegistry singleton binds Srv001Guard —
        // proves the legacy runtime path is untouched. Registry API
        // exposes `registeredCodes()` (see Engine/ServiceSubmissionGuardRegistry).
        $registry = app(\Modules\JeaServices\Engine\ServiceSubmissionGuardRegistry::class);
        $this->assertContains(
            \Modules\JeaServices\Engine\Srv001Guard::SERVICE_CODE,
            $registry->registeredCodes(),
            'Srv001Guard must remain registered — TARGET_RUNTIME_ACTIVATED=NO invariant',
        );
    }

    // ── PUBLICATION_STATUS=BLOCKED ────────────────────────────────────

    public function test_service_publication_still_refuses_srv001_after_submission(): void
    {
        // Run a submission first (so the use case has executed against
        // the service), then attempt to publish. Assert publication is
        // still blocked because the service lacks UAT / real fee / real
        // workflow classification per SG-01 ServicePublicationPolicy.
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-6', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $this->useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        $policy = new \Modules\JeaServices\Governance\ServicePublicationPolicy();
        $decision = $policy->evaluate($this->service->fresh(), actorUserId: $this->applicant->id);
        $this->assertFalse($decision->allowed, 'publication must remain BLOCKED — no UAT, placeholder-fee marker, etc.');
        $this->assertNotEmpty($decision->reasonCodes);
        // Must include at least MISSING_UAT or MISSING_REASON per SG-01
        $this->assertNotEmpty(array_intersect(
            $decision->reasonCodes,
            [
                \Modules\JeaServices\Governance\PublicationDecision::PUB_BLOCKED_MISSING_UAT,
                \Modules\JeaServices\Governance\PublicationDecision::PUB_BLOCKED_MISSING_UAT_REFERENCE,
                \Modules\JeaServices\Governance\PublicationDecision::PUB_BLOCKED_MISSING_REASON,
            ],
        ));
    }

    // ── LEGACY path still LEGACY_UNVERSIONED when no version exists ───

    public function test_legacy_unversioned_classification_when_no_published_version(): void
    {
        // Do NOT publish a version for this service; verify the use case
        // still commits and reports LEGACY_UNVERSIONED classification.
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $result = $this->useCase->execute($app, $this->policy->evaluate($app), $this->applicant);

        $this->assertTrue($result->succeeded);
        $this->assertSame('LEGACY_UNVERSIONED', $result->versionBindingClassification);
        $this->assertNull($result->boundVersionId);
        $this->assertCount(3, $result->snapshotIds, 'snapshots still written even without version binding');
    }

    // ── Committing snapshots preserves the SG-04 unique-constraint invariant ──

    public function test_second_committed_call_for_same_application_violates_unique_and_rolls_back(): void
    {
        (new ServiceVersionPublisher())->publishNewVersion(
            service: $this->service, versionIdentifier: 'v-td02-7', publisher: $this->publisher,
            approvalReference: 'test',
        );
        $app = $this->makeApp(['floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2, 'project_sector' => 'خاص']);
        $decision = $this->policy->evaluate($app);

        $first = $this->useCase->execute($app, $decision, $this->applicant);
        $this->assertTrue($first->succeeded);
        $preCount = CalculationSnapshot::where('application_id', $app->id)->count();

        $second = $this->useCase->execute($app->fresh(), $decision, $this->applicant);
        $this->assertFalse($second->succeeded, 'second commit must fail on SUBMIT unique constraint');
        $this->assertNotNull($second->rollbackReason);
        $this->assertSame(
            $preCount,
            CalculationSnapshot::where('application_id', $app->id)->count(),
            'no additional snapshots inserted',
        );
    }

    // ── Helper ────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function makeApp(array $data): Application
    {
        return Application::create([
            'reference_number'      => 'ref-' . uniqid(),
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
