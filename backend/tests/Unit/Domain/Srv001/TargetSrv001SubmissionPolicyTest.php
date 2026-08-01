<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

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
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;
use Modules\JeaServices\Governance\Srv001\LegacySrv001SubmissionPolicy;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * TD-01 · TargetSrv001SubmissionPolicy — contract compliance +
 * numeric parity with LegacySrv001SubmissionPolicy.
 *
 * The target skeleton exists ONLY to prove the SG-05 typed-decision
 * contract can be satisfied for SRV-001. Numeric outputs must remain
 * identical to legacy per JDG-TD00-02 (SRV001_NUMERIC_BEHAVIOR_CHANGED
 * =NO invariant).
 */
class TargetSrv001SubmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private ServiceDefinition $service;
    private TargetSrv001SubmissionPolicy $target;
    private LegacySrv001SubmissionPolicy $legacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'app', 'email' => 'app@t.test',
            'password'            => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);

        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'            => 'SRV-001',
            'name_ar'         => 'استطلاع الموقع',
            'name_en'         => 'Site Survey',
            'currency'        => 'JOD',
            'schema'          => [
                'workflow'  => [
                    'stages'  => [['id' => 'r', 'role' => 'staff', 'sla_hours' => 24]],
                    'routing' => [[
                        'action'     => 'ROUTE_TO_SERVICE',
                        'target'     => 'SRV-006',
                        'when'       => ['project_sector' => 'حكومي'],
                        'message_ar' => 'المشاريع الحكومية تُقدَّم عبر خدمة SRV-006.',
                    ]],
                ],
                'fields'    => [],
                'documents' => [],
                'fee'       => ['type' => 'per_unit', 'unit' => 'lm', 'rate' => 0.150],
            ],
            'status'          => 'active',
        ]);

        (new Srv001RulesSeeder())->run();

        $this->target = new TargetSrv001SubmissionPolicy(
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
        $this->legacy = new LegacySrv001SubmissionPolicy(
            new LegacyExplorationRequirementMatrixCalculator(),
            new LegacyWellsCountCalculator(),
            new LegacyNetDepthTableCalculator(),
        );
    }

    public function test_service_code_matches(): void
    {
        $this->assertSame('SRV-001', $this->target->serviceCode());
    }

    public function test_status_classification_is_target_domain_provisional(): void
    {
        $this->assertSame('TARGET_DOMAIN_PROVISIONAL', TargetSrv001SubmissionPolicy::STATUS_CLASSIFICATION);
    }

    public function test_government_project_sector_rejected_matches_legacy(): void
    {
        $app = $this->makeApp([
            'project_sector'                 => 'حكومي',
            'floor_count'                    => 3,
            'floor_area'                     => 150,
            'actual_exploration_point_count' => 5,
        ]);

        $targetDecision = $this->target->evaluate($app);
        $legacyDecision = $this->legacy->evaluate($app);

        $this->assertFalse($targetDecision->accepted);
        $this->assertFalse($legacyDecision->accepted);
        $this->assertArrayHasKey('project_sector', $targetDecision->errors);
        $this->assertArrayHasKey('project_sector', $legacyDecision->errors);
    }

    public function test_happy_path_derived_values_match_legacy(): void
    {
        $app = $this->makeApp([
            'project_sector'                 => 'خاص',
            'floor_count'                    => 3,
            'floor_area'                     => 150.0,
            'actual_exploration_point_count' => 2,
        ]);

        $targetDecision = $this->target->evaluate($app);
        $legacyDecision = $this->legacy->evaluate($app);

        $this->assertTrue($targetDecision->accepted, 'target accepted: ' . json_encode($targetDecision->errors));
        $this->assertTrue($legacyDecision->accepted);

        // Numeric derived-value parity — every key the legacy writes must
        // exist in the target output with the same value.
        foreach ($legacyDecision->derivedValues as $key => $value) {
            $this->assertArrayHasKey($key, $targetDecision->derivedValues, "target missing key {$key}");
            $this->assertSame($value, $targetDecision->derivedValues[$key], "value mismatch at {$key}");
        }

        // Target adds the provisional marker (legacy does not).
        $this->assertTrue($targetDecision->derivedValues['target_domain_provisional'] ?? null);
    }

    public function test_below_minimum_rejection_matches_legacy(): void
    {
        $app = $this->makeApp([
            'project_sector'                 => 'خاص',
            'floor_count'                    => 3,
            'floor_area'                     => 500,
            'actual_exploration_point_count' => 1,
        ]);

        $targetDecision = $this->target->evaluate($app);
        $legacyDecision = $this->legacy->evaluate($app);

        $this->assertFalse($targetDecision->accepted);
        $this->assertFalse($legacyDecision->accepted);
        $this->assertArrayHasKey('actual_exploration_point_count', $targetDecision->errors);
        $this->assertArrayHasKey('actual_exploration_point_count', $legacyDecision->errors);
    }

    public function test_special_study_required_matches_legacy(): void
    {
        $app = $this->makeApp([
            'project_sector'                 => 'خاص',
            'floor_count'                    => 3,
            'floor_area'                     => 2000,
            'actual_exploration_point_count' => 8,
        ]);

        $targetDecision = $this->target->evaluate($app);
        $legacyDecision = $this->legacy->evaluate($app);

        $this->assertTrue($targetDecision->accepted);
        $this->assertTrue($legacyDecision->accepted);
        $this->assertSame('SPECIAL_STUDY_REQUIRED', $targetDecision->derivedValues['exploration_requirement_status']);
        $this->assertSame('SPECIAL_STUDY_REQUIRED', $legacyDecision->derivedValues['exploration_requirement_status']);
        $this->assertTrue($targetDecision->derivedValues['technical_review_required']);
        $this->assertTrue($legacyDecision->derivedValues['technical_review_required']);
    }

    public function test_target_does_not_mutate_application_persistent_state(): void
    {
        $app = $this->makeApp([
            'project_sector' => 'خاص', 'floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2,
        ]);
        $originalData = $app->data;

        $this->target->evaluate($app);

        $this->assertSame($originalData, $app->fresh()->data, 'TargetSrv001SubmissionPolicy MUST NOT save');
    }

    public function test_snapshot_payloads_carry_target_domain_provisional_open_decisions(): void
    {
        $app = $this->makeApp([
            'project_sector' => 'خاص', 'floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2,
        ]);
        $decision = $this->target->evaluate($app);

        $this->assertCount(3, $decision->calculationSnapshots);
        foreach ($decision->calculationSnapshots as $snap) {
            $this->assertNotEmpty($snap['open_decisions'] ?? [], 'each target snapshot must carry OD blockers');
            $joined = implode(' | ', $snap['open_decisions']);
            $this->assertStringContainsString('TARGET_DOMAIN_PROVISIONAL', $joined);
        }
    }

    public function test_target_and_legacy_produce_same_snapshot_ruleversion_set(): void
    {
        $app = $this->makeApp([
            'project_sector' => 'خاص', 'floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2,
        ]);

        $targetIds = array_column($this->target->evaluate($app)->calculationSnapshots, 'rule_version_id');
        $legacyIds = array_column($this->legacy->evaluate($app)->calculationSnapshots, 'rule_version_id');

        sort($targetIds);
        sort($legacyIds);
        $this->assertSame($legacyIds, $targetIds);
    }

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
