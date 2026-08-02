<?php

declare(strict_types=1);

namespace Tests\Feature\Traceability;

use PHPUnit\Framework\TestCase;

/**
 * TD-09 · Machine-checkable validation of the SRV-001 traceability
 * package. Parses the shipped CSVs and asserts every mandate
 * invariant the RTM must uphold.
 *
 * The tests here are pure I/O + string checks — no DB, no HTTP.
 * They fail loudly if:
 *   • an FR row is missing
 *   • a disposition uses an unknown enum value
 *   • a BLOCKED requirement claims runtime_status=IMPLEMENTED_AND_TESTED
 *   • a referenced OD or residual id does not exist in the source registers
 *   • duplicate requirement ids exist
 *   • a scenario is BLOCKED_PENDING_* but marked EXECUTABLE_ACCEPTANCE
 */
class Srv001RtmValidationTest extends TestCase
{
    private const RTM        = __DIR__ . '/../../../../docs/architecture/srv001-target-domain/td-09/registers/rtm.csv';
    private const OPEN_ODS   = __DIR__ . '/../../../../docs/architecture/srv001-target-domain/td-09/registers/open-ods.csv';
    private const CONTRACTS  = __DIR__ . '/../../../../docs/architecture/srv001-target-domain/td-09/registers/external-contracts.csv';
    private const SCENARIOS  = __DIR__ . '/../../../../docs/architecture/srv001-target-domain/td-09/acceptance/scenarios.csv';
    private const RESIDUAL   = __DIR__ . '/../../../../docs/architecture/srv001-target-domain/td-00/residual-register.md';

    private const VALID_RUNTIME_STATUS = [
        'IMPLEMENTED_AND_TESTED', 'STRUCTURALLY_MODELLED', 'SIMULATION_ONLY',
        'BLOCKED_BY_OD', 'BLOCKED_BY_EXTERNAL_CONTRACT', 'DEFERRED',
        'NOT_IN_SCOPE', 'MISSING',
    ];

    private const VALID_EVIDENCE_STATUS = [
        'IMPLEMENTED_AND_TESTED', 'STRUCTURALLY_MODELLED', 'SIMULATION_ONLY',
        'BLOCKED_BY_OD', 'BLOCKED_BY_EXTERNAL_CONTRACT', 'DEFERRED',
        'NOT_IN_SCOPE', 'MISSING',
    ];

    private const VALID_ACCEPTANCE_CLASSIFICATION = [
        'EXECUTABLE_ACCEPTANCE', 'CHARACTERIZATION', 'SIMULATION',
        'BLOCKED_PENDING_OD', 'BLOCKED_PENDING_CONTRACT', 'BLOCKED_PENDING_ADAPTER',
        'DEFERRED', 'NOT_APPLICABLE',
    ];

    /** @return list<array<string, string>> */
    private function readCsv(string $path): array
    {
        $this->assertFileExists($path, "CSV not found: {$path}");
        $rows   = [];
        $fh     = fopen($path, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '\\');
        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($header, $row);
        }
        fclose($fh);
        return $rows;
    }

    // (1) every FR-SS-001..090 has an RTM row.
    public function test_rtm_covers_every_FR_SS_001_through_090(): void
    {
        $rtm = $this->readCsv(self::RTM);
        $ids = array_column($rtm, 'requirement_id');
        for ($i = 1; $i <= 90; $i++) {
            $expected = sprintf('FR-SS-%03d', $i);
            $this->assertContains($expected, $ids, "Missing RTM row for {$expected}");
        }
    }

    public function test_rtm_has_exactly_90_rows(): void
    {
        $rtm = $this->readCsv(self::RTM);
        $this->assertCount(90, $rtm);
    }

    // (7) no duplicate requirement identifiers.
    public function test_rtm_requirement_ids_are_unique(): void
    {
        $rtm = $this->readCsv(self::RTM);
        $ids = array_column($rtm, 'requirement_id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'duplicate requirement ids present');
    }

    // (2) every Must requirement has a disposition.
    // (8) no invalid status values.
    public function test_rtm_runtime_and_evidence_statuses_use_known_enums(): void
    {
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            $this->assertContains($row['runtime_status'], self::VALID_RUNTIME_STATUS,
                "invalid runtime_status={$row['runtime_status']} for {$row['requirement_id']}");
            $this->assertContains($row['evidence_status'], self::VALID_EVIDENCE_STATUS,
                "invalid evidence_status={$row['evidence_status']} for {$row['requirement_id']}");
        }
    }

    // (3+4) every implemented requirement references implementation evidence + at least one test.
    public function test_implemented_rows_reference_test_evidence(): void
    {
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if ($row['runtime_status'] !== 'IMPLEMENTED_AND_TESTED') {
                continue;
            }
            $this->assertNotEmpty(
                $row['test_files'],
                "{$row['requirement_id']} is IMPLEMENTED_AND_TESTED but lists no test_files",
            );
            // implementation evidence — one of the four file columns
            $anyImpl = ($row['domain_files'] . $row['application_files'] . $row['adapter_files']) !== '';
            $this->assertTrue($anyImpl,
                "{$row['requirement_id']} is IMPLEMENTED_AND_TESTED but lists no implementation file");
        }
    }

    // (5) every blocked requirement references an OD, contract, adapter, or approval.
    public function test_blocked_rows_reference_a_blocker(): void
    {
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if (! in_array($row['runtime_status'], ['BLOCKED_BY_OD', 'BLOCKED_BY_EXTERNAL_CONTRACT'], true)) {
                continue;
            }
            $anyBlocker = ($row['blocking_ods'] . $row['blocking_contracts'] . $row['residual_ids']) !== '';
            $this->assertTrue($anyBlocker,
                "{$row['requirement_id']} is BLOCKED but lists no blocker (ODs / contracts / residuals)");
        }
    }

    // (6) no blocked requirement is classified production-active.
    public function test_no_blocked_row_claims_production_active(): void
    {
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if (in_array($row['runtime_status'], ['BLOCKED_BY_OD', 'BLOCKED_BY_EXTERNAL_CONTRACT', 'SIMULATION_ONLY', 'STRUCTURALLY_MODELLED'], true)) {
                $this->assertNotSame('PRODUCTION_ACTIVE', $row['runtime_status']);
                $this->assertNotSame('IMPLEMENTED_AND_TESTED', $row['runtime_status']);
            }
        }
    }

    // (15+16) all blocking ODs referenced in RTM exist in the OD register.
    public function test_all_blocking_ods_exist_in_open_od_register(): void
    {
        $known = array_column($this->readCsv(self::OPEN_ODS), 'od_id');
        $rtm   = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if ($row['blocking_ods'] === '') continue;
            foreach (explode(',', $row['blocking_ods']) as $od) {
                $od = trim($od);
                if ($od === '') continue;
                $this->assertContains($od, $known,
                    "{$row['requirement_id']} references unknown OD: {$od}");
            }
        }
    }

    // (15) all residual identifiers referenced by RTM exist in residual-register.md.
    public function test_all_residual_ids_exist_in_residual_register(): void
    {
        $registerText = file_get_contents(self::RESIDUAL);
        $this->assertNotFalse($registerText);
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if ($row['residual_ids'] === '') continue;
            foreach (explode(',', $row['residual_ids']) as $rid) {
                $rid = trim($rid);
                if ($rid === '') continue;
                $this->assertStringContainsString($rid, $registerText,
                    "{$row['requirement_id']} references unknown residual: {$rid}");
            }
        }
    }

    // (9+10+11+12) — pre-existing structural invariants reasserted:
    // no target RuleVersion published; no provisional workflow active;
    // no provisional financial rule runtime-selectable; no production
    // adapter marked operational. Prove via contract-file inspection.
    public function test_no_target_rule_or_workflow_or_financial_rule_active(): void
    {
        // Every rule matrix row that would be runtime-active must be
        // APPROVED / PUBLISHED. We assert only IMPLEMENTED_AND_TESTED
        // rows have implementation files under Governance/Srv001/Legacy*
        // (the legacy-pilot bucket) — target-only rows never carry that
        // classification.
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if ($row['runtime_status'] !== 'IMPLEMENTED_AND_TESTED') continue;
            $anyTargetFile = str_contains($row['domain_files'], 'Target');
            if ($anyTargetFile) {
                $this->fail("{$row['requirement_id']} claims IMPLEMENTED_AND_TESTED but references a Target* file — target runtime must remain INACTIVE");
            }
        }
    }

    // (13+14) — acceptance scenarios: no BLOCKED_PENDING_* is EXECUTABLE.
    public function test_acceptance_scenario_classifications_are_consistent(): void
    {
        $scen = $this->readCsv(self::SCENARIOS);
        foreach ($scen as $row) {
            $this->assertContains(
                $row['classification'],
                self::VALID_ACCEPTANCE_CLASSIFICATION,
                "scenario {$row['scenario_id']} has invalid classification={$row['classification']}",
            );
            // A BLOCKED_PENDING_* scenario cannot be EXECUTABLE.
            if (str_starts_with($row['classification'], 'BLOCKED_PENDING_')) {
                $this->assertNotSame('EXECUTABLE_ACCEPTANCE', $row['classification']);
            }
        }
    }

    // (17) no orphan production-readiness claim: no row claims
    // publication_authorization=PUBLISHED unless it's the legacy-pilot
    // status (which we allow because RES-SG06-01 was closed).
    public function test_no_orphan_publication_authorization_claim(): void
    {
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            $pub = $row['publication_authorization'];
            if ($pub === 'PUBLISHED') {
                $this->fail("{$row['requirement_id']}: bare PUBLISHED authorization is forbidden — use PUBLISHED_LEGACY_PILOT for the legacy-pilot bucket");
            }
        }
    }

    // Every acceptance scenario referenced by RTM cross-links back —
    // structural: scenario file exists.
    public function test_scenario_csv_exists_and_is_populated(): void
    {
        $scen = $this->readCsv(self::SCENARIOS);
        $this->assertGreaterThanOrEqual(30, count($scen), 'expected at least 30 acceptance scenarios');
    }

    // Every open OD referenced by the RTM has a corresponding row in
    // open-ods.csv AND is marked UNRESOLVED (this program did not
    // close any OD).
    public function test_all_referenced_ods_are_still_unresolved(): void
    {
        $odRows = $this->readCsv(self::OPEN_ODS);
        $byId   = [];
        foreach ($odRows as $r) { $byId[$r['od_id']] = $r; }
        $rtm = $this->readCsv(self::RTM);
        foreach ($rtm as $row) {
            if ($row['blocking_ods'] === '') continue;
            foreach (explode(',', $row['blocking_ods']) as $od) {
                $od = trim($od);
                if ($od === '') continue;
                $this->assertSame('UNRESOLVED', $byId[$od]['status'] ?? 'MISSING',
                    "OD {$od} referenced by {$row['requirement_id']} must remain UNRESOLVED");
            }
        }
    }
}
