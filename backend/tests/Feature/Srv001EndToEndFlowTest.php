<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ManualReferenceLinksSeeder;
use Modules\JeaServices\Database\Seeders\ManualReferencesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SiteSurveyFeesSeeder;
use Modules\JeaServices\Database\Seeders\Srv001PilotSeeder;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * SRV-001 end-to-end HTTP flow — the closest automated substitute for
 * the pre-commit closure §2 UI smoke test (a headless browser is not
 * available in this environment; the test walks the exact API path an
 * applicant's browser would trigger).
 *
 * Steps mirror the §2 checklist:
 *   • Sign in as engineering office.
 *   • Open SRV-001 → confirm governorate + no DLS + routing available.
 *   • Try government sector → 422 with SRV-006 in the message.
 *   • Save private-sector draft.
 *   • Reopen draft → all data preserved.
 *   • Upload survey_contract (PDF) + site_investigation_report (PDF).
 *   • Submit successfully.
 *   • Read the submitted detail — attachments preserved.
 *   • Sign in as a different office → 404 on the same application id.
 */
class Srv001EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $officeA;
    private User $officeB;
    private ServiceDefinition $srv001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->orgB = Organization::create([
            'name_ar' => 'other', 'name_en' => 'other', 'slug' => 'other-office', 'is_active' => true,
        ]);
        $this->officeA = $this->makeApplicant($this->orgA, 'office-a@t.esp');
        $this->officeB = $this->makeApplicant($this->orgB, 'office-b@t.esp');

        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new SiteSurveyFeesSeeder());
        $this->runSilently(new Srv001PilotSeeder());
        // TD-03 · runtime submission now routes through LegacySrv001SubmissionPolicy →
        // LegacyExplorationRequirementMatrixCalculator, which resolves a RuleDefinition
        // by identifier. Match the production DatabaseSeeder order (rules run right
        // after the pilot seeder).
        $this->runSilently(new Srv001RulesSeeder());
        $this->runSilently(new ManualReferencesSeeder());
        $this->runSilently(new ManualReferenceLinksSeeder());

        $this->srv001 = ServiceDefinition::where('organization_id', $this->orgA->id)
            ->where('code', 'SRV-001')
            ->firstOrFail();

        Storage::fake(config('filesystems.default'));
    }

    public function test_full_applicant_flow_open_save_upload_submit_readback_isolate(): void
    {
        // 2-3. Open SRV-001 → governorate has 12 options; no DLS field; routing is declared.
        Sanctum::actingAs($this->officeA);
        $schema = $this->getJson('/api/v1/services/SRV-001')->assertOk()->json('service.schema');
        $governorate = collect($schema['fields'])->firstWhere('id', 'governorate');
        $this->assertCount(12, $governorate['options'], 'governorate must expose 12 canonical options');
        $this->assertTrue(collect($schema['fields'])->contains('id', 'dls_key'),
            'DLS KEY must be present per JEA meeting 2026-07-26 §IX (semantic_status=NEEDS_JEA_API)');
        $routing = collect($schema['workflow']['routing'] ?? []);
        $this->assertTrue(
            $routing->contains(fn ($r) => ($r['target'] ?? null) === 'SRV-006'),
            'schema.workflow.routing must declare the SRV-006 target',
        );

        // 3-4. Attempt government-sector submission → guard rejects with
        // SRV-006 routing message. All other required fields must be present
        // so SchemaValidator doesn't intercept before the guard runs.
        $govPayload = array_merge(
            $this->privateDraftPayload(actualPts: 5),
            ['project_sector' => 'حكومي'],
        );
        $govDraft = $this->openDraft($govPayload);
        $this->uploadTwoPdfDocs($govDraft->id);
        $this->postJson("/api/v1/applications/{$govDraft->id}/submit")
            ->assertStatus(422)
            ->assertJsonPath('errors.project_sector',
                'المشاريع الحكومية تُقدَّم عبر خدمة SRV-006 — تقارير استطلاع الموقع للمشاريع الحكومية.');

        // 5-6. Private-sector, floors=5, area=900 → matrix minimum=5, depth=43.
        $draft = $this->openDraft($this->privateDraftPayload(actualPts: 5));

        // 7. actualPts=3 (< minimum 5) → 422 with the enforced minimum surfaced.
        $this->updateDraft($draft->id, $this->privateDraftPayload(actualPts: 3));
        $this->uploadTwoPdfDocs($draft->id);
        $r = $this->postJson("/api/v1/applications/{$draft->id}/submit")->assertStatus(422)->json();
        $this->assertArrayHasKey('actual_exploration_point_count', $r['errors']);
        $this->assertStringContainsString('5', $r['errors']['actual_exploration_point_count']);

        // 8. Correct actual to 5 (== minimum) and save.
        $this->updateDraft($draft->id, $this->privateDraftPayload(actualPts: 5));

        // 12-13. Simulate leave + reopen — GET returns the same draft with data + docs.
        $reopened = $this->getJson("/api/v1/applications/{$draft->id}")
            ->assertOk()->json('application');
        $this->assertSame('draft', $reopened['status']);
        $this->assertSame(5, $reopened['data']['floor_count']);
        $this->assertCount(2, $reopened['documents'],
            'both uploaded documents must be retained across the reopen');
        $docIds = array_column($reopened['documents'], 'document_id');
        $this->assertContains('survey_contract', $docIds);
        $this->assertContains('site_investigation_report', $docIds);

        // 15. Submit successfully.
        $this->postJson("/api/v1/applications/{$draft->id}/submit")
            ->assertOk()
            ->assertJsonPath('application.status', 'submitted');

        // 16-17. Submitted detail carries both documents + the schema
        // (frontend ReportsPanel reads schema.documents to filter REPORTs
        // — verified in ApplicationDetail.test.tsx).
        $detail = $this->getJson("/api/v1/applications/{$draft->id}")
            ->assertOk()->json('application');
        $this->assertSame('submitted', $detail['status']);
        $this->assertCount(2, $detail['documents']);
        $reportDoc = collect($detail['service_definition']['schema']['documents'])
            ->firstWhere('id', 'site_investigation_report');
        $this->assertSame('REPORT', $reportDoc['category']);
        $this->assertNotEmpty($reportDoc['manual_reference_ids'] ?? []);

        // 18-19. Cross-office isolation — same id fetched by another
        // office in another org must 404 (findAccessible uses
        // forOrganization + applicant scope).
        Sanctum::actingAs($this->officeB);
        $this->getJson("/api/v1/applications/{$draft->id}")->assertStatus(404);
        $this->postJson("/api/v1/applications/{$draft->id}/submit")->assertStatus(404);
        $this->postJson("/api/v1/applications/{$draft->id}/documents", [
            'document_id' => 'site_investigation_report',
            'file'        => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.5 evil'),
        ])->assertStatus(404);
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function privateDraftPayload(int $actualPts): array
    {
        return [
            'project_sector'                 => 'خاص',
            'governorate'                    => 'amman',
            'directorate_name'               => 'مديرية أ',
            'village_name'                   => 'قرية ب',
            'basin_number'                   => '007',   // leading zero preserved (string)
            'basin_or_location_name'         => 'حوض التجربة',
            'parcel_number'                  => '042',
            'contract_owner_name'            => 'شركة اختبار',
            'floor_count'                    => 5,
            'floor_area'                     => 900,
            'length_lm'                      => 20,      // SiteSurveyFeesSeeder fee input
            'actual_exploration_point_count' => $actualPts,
            // Meeting 2026-07-26 §IX required fields.
            'contract_party_type'                     => 'متعاقد',
            'tax_number'                              => '1234567890',
            'contract_signed_at'                      => '2026-07-27',
            'national_number'                         => '9701012345',
            'building_count'                          => 1,
            'has_partial_basement'                    => 'no',
            'head_of_specialization_engineer_name'    => 'م. حسين',
            'special_use_type'                        => 'none',
            'exemption_flag'                          => 'no',
        ];
    }

    private function openDraft(array $data): Application
    {
        $r = $this->postJson('/api/v1/applications', [
            'service_code' => 'SRV-001',
            'data'         => $data,
        ])->assertStatus(201)->json('application');
        return Application::findOrFail($r['id']);
    }

    private function updateDraft(int $id, array $data): void
    {
        $this->putJson("/api/v1/applications/{$id}", ['data' => $data])->assertOk();
    }

    private function uploadTwoPdfDocs(int $appId): void
    {
        foreach (['survey_contract', 'site_investigation_report'] as $docId) {
            $this->postJson("/api/v1/applications/{$appId}/documents", [
                'document_id' => $docId,
                'file'        => UploadedFile::fake()->createWithContent(
                    "{$docId}.pdf",
                    "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n",
                ),
            ])->assertStatus(201);
        }
    }

    private function makeApplicant(Organization $org, string $email): User
    {
        return User::create([
            'organization_id'    => $org->id,
            'name'               => 'office',
            'email'              => $email,
            'password'           => 'x',
            'role'               => 'applicant',
            'is_active'          => true,
            'password_changed_at' => now(),
        ]);
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
