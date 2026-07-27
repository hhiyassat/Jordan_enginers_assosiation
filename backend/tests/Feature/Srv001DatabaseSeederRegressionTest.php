<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * REGRESSION for JEA-SRV-001-RUNTIME-SCHEMA-ACTIVATION-DEFECT-01
 *
 * Symptom (2026-07-26 UAT against the running local backend):
 *   • Opening SRV-001 in the SPA showed 0 required documents, no
 *     office-input fields, no cadastral fields, no governorate, no
 *     ReportsPanel — while workflow + fee were correctly displayed.
 *   • Root cause: the local sqlite DB was seeded before ba1068e was
 *     committed, so Srv001PilotSeeder + ManualReferencesSeeder (new
 *     rows) + ManualReferenceLinksSeeder (SRV-001 links) never ran.
 *     The application code was correct; the runtime data was not.
 *
 * The prior per-seeder tests (Srv001PilotSeederTest / Srv001GuardTest)
 * ran a HAND-PICKED subset of seeders in the "right" order and passed
 * — they did NOT catch the class of failure where a deployment /
 * partial re-seed skips Srv001PilotSeeder entirely.
 *
 * This test runs the FULL DatabaseSeeder (the exact composition root
 * that the running app uses) and asserts:
 *   • SRV-001.schema.fields  is non-empty and contains the pilot ids
 *   • SRV-001.schema.documents has exactly 2 required documents
 *   • survey_contract + site_investigation_report both present
 *   • categories CONTRACT + REPORT correctly assigned
 *
 * If any later seeder overwrites the pilot schema, this test fails.
 * If Srv001PilotSeeder is removed from DatabaseSeeder, this test fails.
 * If a fresh install skips Srv001PilotSeeder, this test fails.
 */
class Srv001DatabaseSeederRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_database_seeder_yields_activated_srv001_schema(): void
    {
        $this->seed(DatabaseSeeder::class);

        $org = Organization::where('slug', 'demo')->firstOrFail();
        $srv001 = ServiceDefinition::where('organization_id', $org->id)
            ->where('code', 'SRV-001')
            ->firstOrFail();

        $fields = data_get($srv001->schema, 'fields', []);
        $documents = data_get($srv001->schema, 'documents', []);

        // Field-count invariant: must be non-empty. Zero fields is the
        // exact fingerprint of the observed defect.
        $this->assertGreaterThan(1, count($fields),
            'SRV-001.schema.fields must be non-empty after full DatabaseSeeder — 0 or 1 fields is the runtime-defect fingerprint');

        // Pilot fields must be present.
        $fieldIds = array_column($fields, 'id');
        foreach (['governorate', 'basin_number', 'parcel_number', 'contract_owner_name',
                  'project_sector', 'floor_count', 'floor_area',
                  'actual_exploration_point_count'] as $expected) {
            $this->assertContains($expected, $fieldIds,
                "SRV-001 must expose pilot field {$expected} after full DatabaseSeeder");
        }

        // length_lm must still be there (SiteSurveyFeesSeeder owns it).
        $this->assertContains('length_lm', $fieldIds,
            'length_lm must survive alongside the pilot fields');

        // Document-count invariant: 4 entries (2 base required + 2 conditional
        // for STK-CC-002 owner-match clearance path). Zero is the observed defect.
        $this->assertCount(4, $documents,
            'SRV-001.schema.documents must have 4 entries: 2 base (survey_contract, site_investigation_report) + 2 conditional (previous_office_clearance, previous_office_discharge) after full DatabaseSeeder');
        $docIds = array_column($documents, 'id');
        $this->assertContains('survey_contract', $docIds);
        $this->assertContains('site_investigation_report', $docIds);
        $this->assertContains('previous_office_clearance', $docIds);
        $this->assertContains('previous_office_discharge', $docIds);

        $contract = collect($documents)->firstWhere('id', 'survey_contract');
        $report   = collect($documents)->firstWhere('id', 'site_investigation_report');
        $this->assertSame('CONTRACT', $contract['category']);
        $this->assertSame('REPORT',   $report['category']);
        $this->assertTrue($contract['required']);
        $this->assertTrue($report['required']);

        // Routing block must exist (SRV-006 target).
        $routing = data_get($srv001->schema, 'workflow.routing', []);
        $this->assertNotEmpty($routing);
        $this->assertSame('SRV-006', collect($routing)->firstWhere('action', 'ROUTE_TO_SERVICE')['target']);

        // No later seeder must have flipped SRV-001 into placeholder mode:
        // certificate.validity_months=0 + fields=[] + documents=[] is
        // exactly what ServicePlan2026Seeder installs for a placeholder.
        $this->assertNotEmpty($fields,
            'placeholder schema must NOT survive the full DatabaseSeeder');
    }

    public function test_full_database_seeder_yields_two_required_docs_on_zero_doc_submit(): void
    {
        // §8 of the defect brief: independently of the UI, a zero-doc
        // submission on SRV-001 must return 422. This exercises the
        // end-to-end guard against the exact post-full-seed state.
        $this->seed(DatabaseSeeder::class);

        $org = Organization::where('slug', 'demo')->firstOrFail();
        $srv001 = ServiceDefinition::where('organization_id', $org->id)
            ->where('code', 'SRV-001')
            ->firstOrFail();

        // Any applicant User in the org will do; DemoSeeder creates one.
        $applicant = User::where('organization_id', $org->id)
            ->where('role', 'applicant')
            ->firstOrFail();

        \Laravel\Sanctum\Sanctum::actingAs($applicant);

        $app = \Modules\JeaServices\Models\Application::create([
            'reference_number'      => \Modules\JeaServices\Models\Application::generateReference($srv001),
            'organization_id'       => $org->id,
            'service_definition_id' => $srv001->id,
            'applicant_id'          => $applicant->id,
            'status'                => \Modules\JeaServices\Models\Application::STATUS_DRAFT,
            'data'                  => [], // deliberately empty
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        // Zero fields, zero documents → must 422, not 200.
        $this->postJson("/api/v1/applications/{$app->id}/submit")
            ->assertStatus(422);
    }
}
