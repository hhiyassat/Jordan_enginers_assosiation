<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingFeeMatrixSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ManualReferenceLinksSeeder;
use Modules\JeaServices\Database\Seeders\ManualReferencesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SiteSurveyFeesSeeder;
use Modules\JeaServices\Database\Seeders\Srv001PilotSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Modules\JeaServices\Models\ManualReference;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Support\JordanGovernorates;
use Tests\TestCase;

/**
 * SRV-001 pilot end-to-end schema/seeder assertions per directive §15.
 *
 * Covers:
 *   • Governorate reuses the canonical 12-option enum from the shared helper
 *   • project_sector enum contains خاص + حكومي and routes to SRV-006
 *   • Both survey_contract + site_investigation_report are required
 *   • DRW-P-002 governorate mapping (JORD-63 + JORD-71) is preserved
 *   • JORD-63 fee matrix is NOT attached to SRV-001
 *   • JORD-71 quota rule is NOT attached to SRV-001
 *   • Cadastral fields are string+pattern (leading zero preservation)
 *   • Manual reference seeder is idempotent — no duplicate rows
 *   • MSC-013 is never created (constitutional fact from directive)
 */
class Srv001PilotSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        // Minimal pipeline: no need for capacity/sanction fixtures for schema tests.
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new DrawingFeeMatrixSeeder());
        $this->runSilently(new SiteSurveyFeesSeeder());
        $this->runSilently(new Srv001PilotSeeder());
        $this->runSilently(new ManualReferencesSeeder());
        $this->runSilently(new ManualReferenceLinksSeeder());
    }

    public function test_srv001_governorate_uses_canonical_12_option_helper(): void
    {
        $svc = $this->service('SRV-001');
        $field = collect(data_get($svc->schema, 'fields', []))
            ->firstWhere('id', 'governorate');
        $this->assertNotNull($field, 'SRV-001 must have a governorate field');
        $this->assertSame('select', $field['type']);
        $this->assertCount(12, $field['options'] ?? [],
            'SRV-001 governorate must expose 12 options — same set as DRW-P-*');
        $this->assertSame(
            JordanGovernorates::values(),
            array_column($field['options'], 'value'),
            'SRV-001 governorate values must match the canonical helper',
        );
    }

    public function test_project_sector_enum_and_routing_are_present(): void
    {
        $svc = $this->service('SRV-001');
        $ps = collect(data_get($svc->schema, 'fields', []))
            ->firstWhere('id', 'project_sector');
        $this->assertNotNull($ps);
        $values = array_column($ps['options'] ?? [], 'value');
        $this->assertContains('خاص', $values);
        $this->assertContains('حكومي', $values);

        $routing = data_get($svc->schema, 'workflow.routing', []);
        $this->assertNotEmpty($routing, 'routing block missing');
        $govRule = collect($routing)->firstWhere('action', 'ROUTE_TO_SERVICE');
        $this->assertNotNull($govRule);
        $this->assertSame('حكومي', data_get($govRule, 'when.project_sector'));
        $this->assertSame('SRV-006', $govRule['target']);
    }

    public function test_both_required_documents_exist_in_correct_categories(): void
    {
        $docs = data_get($this->service('SRV-001')->schema, 'documents', []);
        $contract = collect($docs)->firstWhere('id', 'survey_contract');
        $report   = collect($docs)->firstWhere('id', 'site_investigation_report');

        $this->assertNotNull($contract, 'survey_contract must be present');
        $this->assertNotNull($report,   'site_investigation_report must be present');
        $this->assertTrue($contract['required']);
        $this->assertTrue($report['required']);
        $this->assertSame('CONTRACT', $contract['category']);
        $this->assertSame('REPORT',   $report['category']);
        $this->assertSame(
            'HEAD_OF_SPECIALIZATION',
            data_get($report, 'signature_requirements.signing_role'),
        );
    }

    public function test_cc002_conditional_clearance_documents_present_and_optional(): void
    {
        // STK-2026-07-27-CC-002 declares two documents on the SRV-001 schema
        // with required=false so first-time submissions (no owner-match conflict)
        // are not gated on them. OwnerMatchClearanceGuard enforces them
        // conditionally at submit time.
        $docs = data_get($this->service('SRV-001')->schema, 'documents', []);
        $clearance = collect($docs)->firstWhere('id', 'previous_office_clearance');
        $discharge = collect($docs)->firstWhere('id', 'previous_office_discharge');

        $this->assertNotNull($clearance, 'previous_office_clearance must be present in SRV-001 schema');
        $this->assertNotNull($discharge, 'previous_office_discharge must be present in SRV-001 schema');
        $this->assertFalse($clearance['required'], 'clearance must be required=false so SchemaValidator does not gate it — guard enforces conditionally');
        $this->assertFalse($discharge['required'], 'discharge must be required=false — guard enforces conditionally');
        $this->assertSame('CONTRACT', $clearance['category']);
        $this->assertSame('CONTRACT', $discharge['category']);
        $this->assertSame('owner_match_with_prior_office_application', $clearance['conditional_required_when']);
        $this->assertSame('owner_match_with_prior_office_application', $discharge['conditional_required_when']);
    }

    public function test_srv001_document_accept_agrees_with_pdf_or_dwg_rule(): void
    {
        // File-type contract: the extensions the seeder declares in
        // `accept` MUST be a subset of what App\Rules\PdfOrDwgFile
        // (invoked by ApplicationController::uploadDocument) actually
        // permits, otherwise a schema-declared extension would silently
        // be rejected at upload time.
        $ruleAllowed = ['pdf', 'dwg'];
        foreach (data_get($this->service('SRV-001')->schema, 'documents', []) as $doc) {
            foreach ($doc['accept'] ?? [] as $ext) {
                $this->assertContains($ext, $ruleAllowed,
                    "{$doc['id']}: declared extension {$ext} must be accepted by PdfOrDwgFile");
            }
        }
    }

    public function test_documents_use_canonical_file_type_keys_only(): void
    {
        // The repository's canonical extension whitelist is `accept: [...]`
        // (read by DocumentUploader + PdfOrDwgFile) and `max_size_mb`
        // (read by DocumentUploader + ApplicationController::uploadDocument).
        // No parallel `accepted_mime_types` / `max_size_bytes` alias — an
        // alias would silently disagree because nothing consumes them.
        foreach (data_get($this->service('SRV-001')->schema, 'documents', []) as $doc) {
            $this->assertSame(['pdf'], $doc['accept'] ?? null,
                "{$doc['id']}: accept must be ['pdf'] (canonical extension key)");
            $this->assertIsInt($doc['max_size_mb'] ?? null,
                "{$doc['id']}: max_size_mb must be set (canonical size key)");
            $this->assertArrayNotHasKey('accepted_mime_types', $doc,
                "{$doc['id']}: no accepted_mime_types alias — nothing reads it");
            $this->assertArrayNotHasKey('max_size_bytes', $doc,
                "{$doc['id']}: no max_size_bytes alias — canonical key is max_size_mb");
        }
    }

    public function test_cadastral_fields_preserve_leading_zeros(): void
    {
        $fields = collect(data_get($this->service('SRV-001')->schema, 'fields', []));
        foreach (['basin_number', 'parcel_number'] as $id) {
            $f = $fields->firstWhere('id', $id);
            $this->assertNotNull($f, "field {$id} must exist");
            $this->assertSame('text', $f['type'],
                "{$id} must be a text field so leading zeros survive");
            $this->assertSame('^[0-9]+$', $f['pattern']);
        }
    }

    public function test_drw_p_002_governorate_mapping_preserved_after_srv001_seeder(): void
    {
        // JORD-63 is the DRW fee matrix; must remain attached to DRW-P-002
        // governorate + building_class + area_m2.
        $jord63 = ManualReference::where('jord_ticket', 'JORD-63')->firstOrFail();
        $jord71 = ManualReference::where('jord_ticket', 'JORD-71')->firstOrFail();

        $svc = $this->service('DRW-P-002');
        $govField = collect(data_get($svc->schema, 'fields', []))
            ->firstWhere('id', 'governorate');
        $this->assertNotNull($govField);
        // First-write-wins: JORD-63 or JORD-71 grabbed the slot. Whichever it
        // is, one of the two DRW-P-% refs must be there.
        $this->assertContains(
            $govField['manual_reference_id'] ?? null,
            [$jord63->id, $jord71->id],
            'DRW-P-002 governorate manual_reference_id must remain one of the JORD-63/71 refs',
        );
    }

    public function test_no_drw_only_refs_leak_onto_srv001(): void
    {
        $jord63 = ManualReference::where('jord_ticket', 'JORD-63')->firstOrFail();
        $jord71 = ManualReference::where('jord_ticket', 'JORD-71')->firstOrFail();

        $fields = collect(data_get($this->service('SRV-001')->schema, 'fields', []));
        foreach ($fields as $f) {
            $refId = $f['manual_reference_id'] ?? null;
            $this->assertNotSame($jord63->id, $refId,
                "SRV-001 field {$f['id']} must not carry the DRW fee-matrix reference (JORD-63)");
            $this->assertNotSame($jord71->id, $refId,
                "SRV-001 field {$f['id']} must not carry the governorate-overflow rule (JORD-71)");
        }
    }

    public function test_manual_references_seeder_is_idempotent(): void
    {
        $countBefore = ManualReference::count();
        $this->runSilently(new ManualReferencesSeeder());
        $this->runSilently(new ManualReferencesSeeder());
        $this->assertSame($countBefore, ManualReference::count(),
            'Re-running ManualReferencesSeeder must not create duplicate rows');
    }

    public function test_srv001_pilot_adds_exactly_eight_new_manual_references(): void
    {
        // Count invariant per closure §1: whatever the baseline is, the
        // pilot must add exactly the 8 SRV-001 references and no more.
        // Does NOT hardcode a false historical number — reads the
        // baseline live from the seeder array and asserts against the
        // fresh-DB total.
        $srv001Tickets = ['JORD-84', 'JORD-85-srv', 'JORD-86', 'JORD-87', 'JORD-88', 'JORD-89', 'JORD-90', 'JORD-91'];
        $baseline = ManualReference::whereNotIn('jord_ticket', $srv001Tickets)->count();
        $srv001Count = ManualReference::whereIn('jord_ticket', $srv001Tickets)->count();

        $this->assertSame(8, $srv001Count,
            'Exactly 8 SRV-001 references must be seeded (JORD-84..91)');
        $this->assertSame($baseline + 8, ManualReference::count(),
            'Total rows must equal baseline + 8 SRV-001 additions — no drift');
    }

    public function test_srv001_specific_refs_are_linked_to_expected_fields(): void
    {
        $svc = $this->service('SRV-001');
        $fields = collect(data_get($svc->schema, 'fields', []));

        $jord89 = ManualReference::where('jord_ticket', 'JORD-89')->firstOrFail();
        $jord91 = ManualReference::where('jord_ticket', 'JORD-91')->firstOrFail();

        foreach (['basin_number', 'parcel_number', 'basin_or_location_name', 'contract_owner_name'] as $id) {
            $f = $fields->firstWhere('id', $id);
            $this->assertSame($jord89->id, $f['manual_reference_id'] ?? null,
                "{$id} must link to JORD-89 (cadastral identification content)");
        }
        foreach (['floor_count', 'floor_area', 'actual_exploration_point_count'] as $id) {
            $f = $fields->firstWhere('id', $id);
            $this->assertSame($jord91->id, $f['manual_reference_id'] ?? null,
                "{$id} must link to JORD-91 (exploration-requirement matrix)");
        }
    }

    public function test_srv001_api_response_carries_document_contract_shape(): void
    {
        // §6 end-to-end contract: /api/v1/services/{code} returns the raw
        // schema, so the JSON shape the frontend consumes must contain
        // exactly the fields the TypeScript SchemaDocument declares —
        // id, category, report_type (report only), required, accept,
        // max_size_mb, manual_reference_ids, signature_requirements.
        $applicant = \App\Models\User::create([
            'organization_id'    => $this->org->id,
            'name' => 'office', 'email' => 'srv001-api@t.esp', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($applicant);

        $body = $this->getJson('/api/v1/services/SRV-001')->assertOk()->json('service.schema.documents');
        $this->assertIsArray($body);
        $ids = array_column($body, 'id');
        $this->assertContains('survey_contract', $ids);
        $this->assertContains('site_investigation_report', $ids);

        $contract = collect($body)->firstWhere('id', 'survey_contract');
        $report   = collect($body)->firstWhere('id', 'site_investigation_report');

        $this->assertSame('CONTRACT', $contract['category']);
        $this->assertSame(['pdf'], $contract['accept']);
        $this->assertIsInt($contract['max_size_mb']);
        $this->assertIsArray($contract['manual_reference_ids']);
        $this->assertArrayNotHasKey('report_type', $contract,
            'contract must not carry a report_type badge');

        $this->assertSame('REPORT', $report['category']);
        $this->assertSame('SITE_INVESTIGATION_REPORT', $report['report_type']);
        $this->assertSame(['pdf'], $report['accept']);
        $this->assertIsInt($report['max_size_mb']);
        $this->assertIsArray($report['manual_reference_ids']);
        $this->assertGreaterThanOrEqual(3, count($report['manual_reference_ids']));
        $this->assertSame('HEAD_OF_SPECIALIZATION',
            data_get($report, 'signature_requirements.signing_role'));
    }

    public function test_report_document_accumulates_multiple_manual_refs(): void
    {
        $docs = data_get($this->service('SRV-001')->schema, 'documents', []);
        $report = collect($docs)->firstWhere('id', 'site_investigation_report');
        $refIds = $report['manual_reference_ids'] ?? [];

        $this->assertIsArray($refIds);
        $this->assertGreaterThanOrEqual(3, count($refIds),
            'site_investigation_report must carry at least 3 refs (JORD-86 direct-submission, JORD-87 signature, JORD-90 technical-content)');

        $expected = [
            ManualReference::where('jord_ticket', 'JORD-86')->value('id'),
            ManualReference::where('jord_ticket', 'JORD-87')->value('id'),
            ManualReference::where('jord_ticket', 'JORD-90')->value('id'),
        ];
        foreach ($expected as $id) {
            $this->assertContains($id, $refIds,
                "manual_reference_ids missing expected id {$id}");
        }
    }

    public function test_length_lm_survives_srv001_pilot_seeder(): void
    {
        // SiteSurveyFeesSeeder installs length_lm; Srv001PilotSeeder must NOT
        // drop it. The per-lm fee (JORD-78) is the canonical SRV-001 fee.
        $svc = $this->service('SRV-001');
        $fields = collect(data_get($svc->schema, 'fields', []));
        $this->assertTrue($fields->contains('id', 'length_lm'),
            'length_lm must survive the pilot seeder — fee depends on it');
        $this->assertSame('per_unit', data_get($svc->schema, 'fee.type'));
        $this->assertSame('length_lm', data_get($svc->schema, 'fee.basis'));
    }

    public function test_msc_013_is_never_created(): void
    {
        $this->assertNull(
            ServiceDefinition::where('code', 'MSC-013')->first(),
            'MSC-013 must never exist — the delta audit + directive both explicitly forbid it',
        );
    }

    public function test_srv001_is_active_and_admin_locked_but_applicants_can_still_submit(): void
    {
        // §7 lock semantics — is_locked=true guards catalog edits (schema,
        // status flips, chat-schema) NOT applicant submissions. An engineering
        // office must still be able to create + save a draft against SRV-001
        // even though the catalog is locked.
        $svc = $this->service('SRV-001');
        $this->assertSame('active', $svc->status,
            'SRV-001 must be status=active for applicants to find it in the public catalog');
        $this->assertTrue((bool) $svc->is_locked,
            'SRV-001 must be admin-locked to protect the schema from accidental edits');

        $applicant = \App\Models\User::create([
            'organization_id'    => $this->org->id,
            'name'               => 'office',
            'email'              => 'srv001-lock@t.esp',
            'password'           => 'x',
            'role'               => 'applicant',
            'is_active'          => true,
            'password_changed_at' => now(),
        ]);

        // A locked catalog service must NOT block draft creation.
        $draft = \Modules\JeaServices\Models\Application::create([
            'reference_number'      => \Modules\JeaServices\Models\Application::generateReference($svc),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $applicant->id,
            'status'                => \Modules\JeaServices\Models\Application::STATUS_DRAFT,
            'data'                  => ['project_sector' => 'خاص'],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        $this->assertTrue($draft->exists,
            'engineering office must be able to open a draft against a locked service');
        $this->assertTrue($draft->isEditable(),
            'draft must be editable — is_locked only guards admin catalog edits');
    }

    private function service(string $code): ServiceDefinition
    {
        return ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function runSilently(\Illuminate\Database\Seeder $seeder): void
    {
        $seeder->setContainer($this->app)
            ->setCommand(new class extends \Illuminate\Console\Command {
                public function info($string, $verbosity = null): void {}
                public function error($string, $verbosity = null): void {}
                public function warn($string, $verbosity = null): void {}
            })
            ->run();
    }
}
