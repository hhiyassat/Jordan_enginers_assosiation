<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use Modules\JeaServices\Domain\Cutover\CutoverChecklist;
use Modules\JeaServices\Domain\Cutover\MigrationDryRunTool;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Srv001CutoverAndMigrationTest extends TestCase
{
    // (15) migration dry-run writes zero rows.
    public function test_dry_run_tool_defaults_to_read_only_and_writes_zero_rows(): void
    {
        $tool = new MigrationDryRunTool();
        $this->assertTrue($tool->isReadOnly());
        $tool->backfillApplication(['id' => 1, 'status' => 'submitted']);
        $tool->backfillApplication(['id' => 2, 'status' => 'draft']);
        $this->assertSame(0, $tool->writeCount());
    }

    // (16) migration tooling refuses write mode without authorization.
    public function test_write_mode_refuses_without_authorization_token(): void
    {
        $this->expectException(RuntimeException::class);
        new MigrationDryRunTool(enableWrite: true, authorizationToken: null);
    }

    public function test_write_mode_refuses_with_incorrect_token(): void
    {
        $this->expectException(RuntimeException::class);
        new MigrationDryRunTool(enableWrite: true, authorizationToken: 'wrong-token');
    }

    // Test-only authorization exists to prove the shape works,
    // but is never granted to production.
    public function test_test_only_authorization_enables_write(): void
    {
        $tool = new MigrationDryRunTool(enableWrite: true, authorizationToken: 'test-authorization-token');
        $this->assertFalse($tool->isReadOnly());
    }

    // (17) rollback plan covers every activation surface.
    // The rollback plan is a documentation artefact; we assert here
    // that every activation-surface item enumerated in TD-10-report
    // has a matching CutoverChecklist item (structural cross-check).
    public function test_cutover_checklist_covers_every_documented_activation_surface(): void
    {
        $items = CutoverChecklist::items();
        // Spot-check: every high-risk activation surface has an entry.
        foreach ([
            'signed_srs_baseline_2_0', 'approved_workflow_version',
            'approved_calculation_rule_versions', 'approved_financial_rule_versions',
            'approved_attachment_limits', 'oracle_contract', 'dls_contract',
            'bura_contracts', 'storage_adapter', 'malware_scanner',
            'payment_gateway', 'receipt_authority', 'certificate_authority',
            'uat_approval', 'migration_dry_run_approval', 'rollback_rehearsal',
            'publication_approval', 'production_change_approval',
        ] as $required) {
            $this->assertContains($required, $items,
                "CutoverChecklist missing required activation-surface item: {$required}");
        }
        $this->assertGreaterThanOrEqual(25, count($items));
    }

    // (18) cutover checklist fails with open blocking ODs (no
    // signed_od_closures + no exceptions → NOT_CUTOVER_READY).
    public function test_checklist_fails_when_od_closures_missing_and_no_exceptions(): void
    {
        $state = array_fill_keys(CutoverChecklist::items(), true);
        $state['signed_od_closures'] = false;
        $r = (new CutoverChecklist())->evaluate($state);
        $this->assertSame(CutoverChecklist::VERDICT_NOT_READY, $r['verdict']);
        $this->assertContains('signed_od_closures', $r['missing']);
    }

    // (19) cutover checklist fails without signed baseline 2.0.
    public function test_checklist_fails_without_signed_baseline_2_0(): void
    {
        $state = array_fill_keys(CutoverChecklist::items(), true);
        $state['signed_srs_baseline_2_0'] = false;
        $r = (new CutoverChecklist())->evaluate($state);
        $this->assertSame(CutoverChecklist::VERDICT_NOT_READY, $r['verdict']);
    }

    // (20) cutover checklist fails without UAT approval.
    public function test_checklist_fails_without_uat_approval(): void
    {
        $state = array_fill_keys(CutoverChecklist::items(), true);
        $state['uat_approval'] = false;
        $r = (new CutoverChecklist())->evaluate($state);
        $this->assertSame(CutoverChecklist::VERDICT_NOT_READY, $r['verdict']);
    }

    // (21) cutover checklist fails without production contracts.
    public function test_checklist_fails_without_production_contracts(): void
    {
        $state = array_fill_keys(CutoverChecklist::items(), true);
        $state['oracle_contract'] = false;
        $state['dls_contract']    = false;
        $state['bura_contracts']  = false;
        $r = (new CutoverChecklist())->evaluate($state);
        $this->assertSame(CutoverChecklist::VERDICT_NOT_READY, $r['verdict']);
    }

    // (22) target remains unpublished and inactive — asserted by
    // the entire prior test suite; here we assert the checklist
    // does NOT pass with all-false (a defensive guard against the
    // classic empty-input bug).
    public function test_checklist_with_no_state_returns_NOT_READY_and_lists_all_items(): void
    {
        $r = (new CutoverChecklist())->evaluate([]);
        $this->assertSame(CutoverChecklist::VERDICT_NOT_READY, $r['verdict']);
        $this->assertSame(CutoverChecklist::items(), $r['missing']);
    }

    // Signed exceptions can move a partial checklist to
    // READY_WITH_SIGNED_EXCEPTIONS — proves the middle verdict exists.
    public function test_signed_exceptions_can_permit_ready_with_exceptions(): void
    {
        $state = array_fill_keys(CutoverChecklist::items(), true);
        $state['performance_evidence'] = false;
        $r = (new CutoverChecklist())->evaluate(
            $state,
            signedExceptions: ['performance_evidence'],
        );
        $this->assertSame(CutoverChecklist::VERDICT_READY_WITH_EXCEPTIONS, $r['verdict']);
    }

    public function test_full_checklist_true_returns_CUTOVER_READY(): void
    {
        $r = (new CutoverChecklist())->evaluate(array_fill_keys(CutoverChecklist::items(), true));
        $this->assertSame(CutoverChecklist::VERDICT_READY, $r['verdict']);
    }

    // Current-reality assertion: today's cutover verdict is NOT_READY.
    // This test documents the current state; when future work satisfies
    // items, the state map here must be updated.
    public function test_current_cutover_state_is_NOT_READY(): void
    {
        $current = array_fill_keys(CutoverChecklist::items(), false);
        $r = (new CutoverChecklist())->evaluate($current);
        $this->assertSame(CutoverChecklist::VERDICT_NOT_READY, $r['verdict']);
    }
}
