<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Governance\CalculationSnapshotWriter;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\CalculationSnapshot;
use Modules\JeaServices\Models\RuleDefinition;
use Modules\JeaServices\Models\RuleVersion;
use Modules\JeaServices\Models\ServiceDefinition;
use RuntimeException;
use Tests\TestCase;

/**
 * SG-04 · rule-provenance seeding + snapshot write policy.
 */
class CalculationSnapshotWriterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private ServiceDefinition $service;
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'u', 'email' => 'u@t.test', 'password' => 'x',
            'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'SRV-001', 'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD',
            'schema' => ['workflow' => ['stages' => [['id' => 'r', 'role' => 'staff']]], 'fields' => [], 'documents' => [], 'fee' => ['type' => 'fixed', 'amount' => 25]],
            'status' => 'active',
        ]);
        $this->application = Application::create([
            'reference_number' => 'r-' . uniqid(),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->user->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [], 'fee_amount' => 0, 'payment_status' => 'pending',
        ]);

        (new Srv001RulesSeeder())->run();
    }

    public function test_seeder_creates_three_rule_definitions(): void
    {
        $this->assertCount(3, RuleDefinition::query()->whereIn('rule_identifier', [
            'SRV001_EXPLORATION_MATRIX',
            'SRV001_WELLS_COUNT',
            'SRV001_NET_DEPTH',
        ])->get());
    }

    public function test_seeder_classifies_wells_and_net_depth_as_provisional(): void
    {
        $wells = RuleDefinition::query()->where('rule_identifier', 'SRV001_WELLS_COUNT')->firstOrFail();
        $this->assertSame(RuleVersion::STATUS_PROVISIONAL, $wells->versions()->firstOrFail()->business_approval_status);

        $matrix = RuleDefinition::query()->where('rule_identifier', 'SRV001_EXPLORATION_MATRIX')->firstOrFail();
        $this->assertSame(RuleVersion::STATUS_APPROVED, $matrix->versions()->firstOrFail()->business_approval_status);
    }

    public function test_draft_snapshot_overwrites_existing_draft(): void
    {
        $writer = new CalculationSnapshotWriter();
        $rv = $this->wellsRuleVersion();

        $first = $writer->writeForDraft($this->application, $rv, ['area_m2' => 500], ['wells_count' => 3]);
        $second = $writer->writeForDraft($this->application, $rv, ['area_m2' => 700], ['wells_count' => 4]);

        $this->assertSame($first->id, $second->id, 'draft snapshot should overwrite in place');
        $this->assertSame(['area_m2' => 700], $second->fresh()->inputs);
        $this->assertSame(['wells_count' => 4], $second->fresh()->outputs);
    }

    public function test_submit_snapshot_is_immutable(): void
    {
        $writer = new CalculationSnapshotWriter();
        $rv = $this->wellsRuleVersion();

        $snap = $writer->writeForSubmit($this->application, $rv, ['area_m2' => 500], ['wells_count' => 3]);

        $snap->outputs = ['wells_count' => 999];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Immutable after insert/');
        $snap->save();
    }

    public function test_submit_snapshot_unique_constraint_rejects_duplicates(): void
    {
        $writer = new CalculationSnapshotWriter();
        $rv = $this->wellsRuleVersion();

        $writer->writeForSubmit($this->application, $rv, ['area_m2' => 500], ['wells_count' => 3]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $writer->writeForSubmit($this->application, $rv, ['area_m2' => 600], ['wells_count' => 4]);
    }

    public function test_manual_recalc_supersedes_submit_snapshot(): void
    {
        $writer = new CalculationSnapshotWriter();
        $rv = $this->wellsRuleVersion();

        $submit = $writer->writeForSubmit($this->application, $rv, ['area_m2' => 500], ['wells_count' => 3]);
        $recalc = $writer->writeForManualRecalc($this->application, $rv, ['area_m2' => 500], ['wells_count' => 4], supersedes: $submit);

        $this->assertSame($submit->id, $recalc->superseded_snapshot_id);
        $this->assertSame(CalculationSnapshot::PURPOSE_MANUAL_RECALC, $recalc->purpose);
        // Submit snapshot remains intact
        $this->assertSame(['wells_count' => 3], $submit->fresh()->outputs);
    }

    public function test_manual_recalc_rejects_superseding_a_draft(): void
    {
        $writer = new CalculationSnapshotWriter();
        $rv = $this->wellsRuleVersion();

        $draft = $writer->writeForDraft($this->application, $rv, ['area_m2' => 500], ['wells_count' => 3]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must supersede a SUBMIT/');
        $writer->writeForManualRecalc($this->application, $rv, ['area_m2' => 500], ['wells_count' => 4], supersedes: $draft);
    }

    public function test_historical_reproduction_query_returns_submit_and_manual_recalcs(): void
    {
        $writer = new CalculationSnapshotWriter();
        $rv = $this->wellsRuleVersion();

        $submit = $writer->writeForSubmit($this->application, $rv, ['area_m2' => 500], ['wells_count' => 3]);
        $recalc = $writer->writeForManualRecalc($this->application, $rv, ['area_m2' => 500], ['wells_count' => 4], supersedes: $submit);
        // Also add a DRAFT snapshot — should NOT appear in historical view
        $writer->writeForDraft($this->application, $rv, ['area_m2' => 300], ['wells_count' => 3]);

        $history = CalculationSnapshot::query()
            ->where('application_id', $this->application->id)
            ->whereIn('purpose', [CalculationSnapshot::PURPOSE_SUBMIT, CalculationSnapshot::PURPOSE_MANUAL_RECALC])
            ->orderBy('calculated_at')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $history);
        $this->assertSame([$submit->id, $recalc->id], $history->pluck('id')->all());
    }

    private function wellsRuleVersion(): RuleVersion
    {
        return RuleVersion::query()
            ->whereHas('ruleDefinition', fn ($q) => $q->where('rule_identifier', 'SRV001_WELLS_COUNT'))
            ->firstOrFail();
    }
}
