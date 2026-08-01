<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;
use Modules\JeaServices\Governance\Srv001\LegacySrv001SubmissionPolicy;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * SG-06 · verifies LegacySrv001SubmissionPolicy produces the same
 * externally observable derived-value set as Srv001Guard would, without
 * mutating the passed Application or writing to the DB.
 */
class LegacySrv001SubmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private ServiceDefinition $service;
    private LegacySrv001SubmissionPolicy $policy;

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
                    'stages' => [['id' => 'r', 'role' => 'staff', 'sla_hours' => 24]],
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

        $this->policy = new LegacySrv001SubmissionPolicy(
            new LegacyExplorationRequirementMatrixCalculator(),
            new LegacyWellsCountCalculator(),
            new LegacyNetDepthTableCalculator(),
        );
    }

    public function test_service_code_and_status_classification(): void
    {
        $this->assertSame('SRV-001', $this->policy->serviceCode());
        $this->assertSame(
            'LEGACY_PILOT_PENDING_BUSINESS_APPROVAL',
            LegacySrv001SubmissionPolicy::STATUS_CLASSIFICATION,
        );
    }

    public function test_government_project_sector_is_rejected_with_routing_hint(): void
    {
        $app = $this->makeApp([
            'project_sector'                  => 'حكومي',
            'floor_count'                     => 3,
            'floor_area'                      => 150,
            'actual_exploration_point_count'  => 5,
        ]);

        $decision = $this->policy->evaluate($app);

        $this->assertFalse($decision->accepted);
        $this->assertArrayHasKey('project_sector', $decision->errors);
        $this->assertStringContainsString('SRV-006', $decision->errors['project_sector'][0]);
    }

    public function test_happy_path_produces_derived_values_and_snapshots(): void
    {
        $app = $this->makeApp([
            'project_sector'                  => 'خاص',
            'floor_count'                     => 3,
            'floor_area'                      => 150.0, // §8 example 1 — expects 2 points, 10 lm
            'actual_exploration_point_count'  => 2,
        ]);

        $decision = $this->policy->evaluate($app);

        $this->assertTrue($decision->accepted, 'expected accepted, got errors: ' . json_encode($decision->errors));
        $this->assertSame('CALCULATED', $decision->derivedValues['exploration_requirement_status']);
        $this->assertSame(2, $decision->derivedValues['minimum_exploration_point_count']);
        $this->assertSame(10, $decision->derivedValues['minimum_total_depth_lm']);
        $this->assertFalse($decision->derivedValues['technical_review_required']);
        $this->assertCount(3, $decision->calculationSnapshots, 'matrix + wells + net-depth');
    }

    public function test_below_minimum_exploration_points_is_rejected(): void
    {
        // §8 example 2: floors ≤3, area 200-600 → 3 points required
        $app = $this->makeApp([
            'project_sector'                  => 'خاص',
            'floor_count'                     => 3,
            'floor_area'                      => 500,
            'actual_exploration_point_count'  => 1, // below minimum of 3
        ]);

        $decision = $this->policy->evaluate($app);

        $this->assertFalse($decision->accepted);
        $this->assertArrayHasKey('actual_exploration_point_count', $decision->errors);
        $this->assertStringContainsString('(1)', $decision->errors['actual_exploration_point_count'][0]);
        $this->assertStringContainsString('(3)', $decision->errors['actual_exploration_point_count'][0]);
    }

    public function test_special_study_required_is_accepted_with_flag(): void
    {
        // area > 1200 triggers SPECIAL_STUDY_REQUIRED
        $app = $this->makeApp([
            'project_sector'                  => 'خاص',
            'floor_count'                     => 3,
            'floor_area'                      => 2000,
            'actual_exploration_point_count'  => 8,
        ]);

        $decision = $this->policy->evaluate($app);

        $this->assertTrue($decision->accepted);
        $this->assertSame('SPECIAL_STUDY_REQUIRED', $decision->derivedValues['exploration_requirement_status']);
        $this->assertTrue($decision->derivedValues['technical_review_required']);
        $this->assertNotEmpty($decision->warnings);
    }

    public function test_policy_does_not_mutate_application_persistent_state(): void
    {
        $app = $this->makeApp([
            'project_sector' => 'خاص', 'floor_count' => 3, 'floor_area' => 150, 'actual_exploration_point_count' => 2,
        ]);
        $originalData = $app->data;

        $this->policy->evaluate($app);

        $this->assertSame($originalData, $app->fresh()->data, 'policy MUST NOT mutate persisted data');
    }

    public function test_snapshot_payloads_reference_correct_rule_versions(): void
    {
        $app = $this->makeApp([
            'project_sector'                 => 'خاص',
            'floor_count'                    => 3,
            'floor_area'                     => 150,
            'actual_exploration_point_count' => 2,
        ]);
        $decision = $this->policy->evaluate($app);

        $ruleIdentifiers = [];
        foreach ($decision->calculationSnapshots as $snap) {
            $version = \Modules\JeaServices\Models\RuleVersion::query()->find($snap['rule_version_id']);
            $ruleIdentifiers[] = $version->ruleDefinition->rule_identifier;
        }
        $this->assertContains('SRV001_EXPLORATION_MATRIX', $ruleIdentifiers);
        $this->assertContains('SRV001_WELLS_COUNT', $ruleIdentifiers);
        $this->assertContains('SRV001_NET_DEPTH', $ruleIdentifiers);
    }

    public function test_provisional_calculators_surface_open_decisions_in_snapshots(): void
    {
        $app = $this->makeApp([
            'project_sector'                 => 'خاص',
            'floor_count'                    => 3,
            'floor_area'                     => 150,
            'actual_exploration_point_count' => 2,
        ]);
        $decision = $this->policy->evaluate($app);

        $wellsPayload = null;
        $netDepthPayload = null;
        foreach ($decision->calculationSnapshots as $snap) {
            $version = \Modules\JeaServices\Models\RuleVersion::query()->find($snap['rule_version_id']);
            $rule = $version->ruleDefinition->rule_identifier;
            if ($rule === 'SRV001_WELLS_COUNT') {
                $wellsPayload = $snap;
            }
            if ($rule === 'SRV001_NET_DEPTH') {
                $netDepthPayload = $snap;
            }
        }

        $this->assertNotNull($wellsPayload);
        $this->assertNotEmpty($wellsPayload['open_decisions'] ?? []);
        $this->assertNotNull($netDepthPayload);
        $this->assertNotEmpty($netDepthPayload['open_decisions'] ?? []);
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
