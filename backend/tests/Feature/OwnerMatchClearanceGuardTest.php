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
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SiteSurveyFeesSeeder;
use Modules\JeaServices\Database\Seeders\Srv001PilotSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Modules\JeaServices\Engine\CrossCuttingSubmissionPipeline;
use Modules\JeaServices\Engine\OwnerMatchClearanceGuard;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationDocument;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * OwnerMatchClearanceGuard — STK-2026-07-27-CC-002 platform-wide gate.
 *
 * Pins:
 *   • No conflict at all → pass
 *   • Different-owner conflict → this guard passes (CadastralConflictGuard
 *     has already rejected; test both guards in the E2E scenario)
 *   • Same-owner conflict + no clearance docs → both docs missing errors
 *   • Same-owner conflict + only clearance uploaded → discharge missing
 *   • Same-owner conflict + both uploaded → pass
 *   • Out-of-scope service (no cadastral fields) → pass
 *   • Arabic normalization for owner-name matching
 *   • Pipeline registration includes this guard
 */
class OwnerMatchClearanceGuardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $officeA;
    private User $officeB;
    private ServiceDefinition $srv001;
    private ServiceDefinition $srv001OrgB;

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

        $this->srv001 = ServiceDefinition::where('organization_id', $this->orgA->id)
            ->where('code', 'SRV-001')
            ->firstOrFail();
        $this->srv001OrgB = $this->srv001->replicate();
        $this->srv001OrgB->organization_id = $this->orgB->id;
        $this->srv001OrgB->code = 'SRV-001-B';
        $this->srv001OrgB->save();

        Storage::fake(config('filesystems.default'));
    }

    // ── guard-level behavior ─────────────────────────────────────────

    public function test_pass_when_no_prior_application(): void
    {
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->payloadWithOwner('شركة اختبار'));
        $this->assertSame([], (new OwnerMatchClearanceGuard())->validate($app));
    }

    public function test_pass_when_different_owner_conflict_exists(): void
    {
        // orgB has a prior app with a DIFFERENT owner
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('مالك آخر'));
        // orgA's app with different owner → CadastralConflictGuard would reject;
        // OwnerMatchClearanceGuard passes because owner doesn't match.
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->payloadWithOwner('شركة اختبار'));
        $this->assertSame([], (new OwnerMatchClearanceGuard())->validate($app));
    }

    public function test_reject_when_same_owner_conflict_and_no_clearance_docs(): void
    {
        // Same owner name across both orgs
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('شركة اختبار'));
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->payloadWithOwner('شركة اختبار'));

        $errors = (new OwnerMatchClearanceGuard())->validate($app);
        $this->assertArrayHasKey('previous_office_clearance', $errors);
        $this->assertArrayHasKey('previous_office_discharge', $errors);
        $this->assertStringContainsString('مخالصة من المكتب الهندسي السابق', $errors['previous_office_clearance']);
        $this->assertStringContainsString('براءة ذمة رسمية', $errors['previous_office_discharge']);
    }

    public function test_reject_when_same_owner_conflict_and_only_clearance_uploaded(): void
    {
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('شركة اختبار'));
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->payloadWithOwner('شركة اختبار'));
        $this->attachDocument($app, 'previous_office_clearance');
        $app->load('documents');

        $errors = (new OwnerMatchClearanceGuard())->validate($app);
        $this->assertArrayNotHasKey('previous_office_clearance', $errors);
        $this->assertArrayHasKey('previous_office_discharge', $errors);
    }

    public function test_pass_when_same_owner_conflict_and_both_docs_uploaded(): void
    {
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('شركة اختبار'));
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->payloadWithOwner('شركة اختبار'));
        $this->attachDocument($app, 'previous_office_clearance');
        $this->attachDocument($app, 'previous_office_discharge');
        $app->load('documents');

        $this->assertSame([], (new OwnerMatchClearanceGuard())->validate($app));
    }

    public function test_out_of_scope_when_service_lacks_cadastral_fields(): void
    {
        $svc = ServiceDefinition::create([
            'organization_id' => $this->orgA->id,
            'code' => 'CERT-TEST-2',
            'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD',
            'schema' => ['fields' => [
                ['id' => 'some_other_field', 'type' => 'text', 'required' => true, 'label_ar' => 'x'],
            ]],
        ]);
        $app = $this->makeDraft($svc, $this->orgA, $this->officeA, ['some_other_field' => 'x']);
        $this->assertSame([], (new OwnerMatchClearanceGuard())->validate($app));
    }

    public function test_owner_name_matches_despite_alef_variants(): void
    {
        // orgB owner uses alef-with-hamza-above; orgA uses plain alef
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('شركة أحمد'));
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->payloadWithOwner('شركة احمد'));

        $errors = (new OwnerMatchClearanceGuard())->validate($app);
        $this->assertArrayHasKey('previous_office_clearance', $errors,
            'Arabic normalization should match owner names despite alef variant differences');
    }

    // ── pipeline registration ────────────────────────────────────────

    public function test_container_pipeline_includes_this_guard(): void
    {
        $pipeline = app(CrossCuttingSubmissionPipeline::class);
        $this->assertContains(
            OwnerMatchClearanceGuard::class,
            $pipeline->registeredGuards(),
            'JeaServicesServiceProvider must register OwnerMatchClearanceGuard in the cross-cutting pipeline',
        );
    }

    // ── end-to-end HTTP integration ──────────────────────────────────

    public function test_submit_endpoint_rejects_when_owner_matches_and_clearance_docs_missing(): void
    {
        // orgB has prior submitted app with same owner
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('شركة اختبار'));

        Sanctum::actingAs($this->officeA);
        $draft = $this->createDraftViaApi($this->payloadWithOwner('شركة اختبار') + [
            'project_sector'                 => 'خاص',
            'governorate'                    => 'amman',
            'directorate_name'               => 'مديرية أ',
            'village_name'                   => 'قرية ب',
            'floor_count'                    => 5,
            'floor_area'                     => 900,
            'length_lm'                      => 20,
            'actual_exploration_point_count' => 5,
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
        ]);
        $this->uploadPdf($draft->id, 'survey_contract');
        $this->uploadPdf($draft->id, 'site_investigation_report');
        // Deliberately DO NOT upload clearance + discharge

        $r = $this->postJson("/api/v1/applications/{$draft->id}/submit")
            ->assertStatus(422)
            ->json();
        $this->assertArrayHasKey('previous_office_clearance', $r['errors']);
        $this->assertArrayHasKey('previous_office_discharge', $r['errors']);
    }

    public function test_submit_endpoint_accepts_when_owner_matches_and_both_docs_uploaded(): void
    {
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->payloadWithOwner('شركة اختبار'));

        Sanctum::actingAs($this->officeA);
        $draft = $this->createDraftViaApi($this->payloadWithOwner('شركة اختبار') + [
            'project_sector'                 => 'خاص',
            'governorate'                    => 'amman',
            'directorate_name'               => 'مديرية أ',
            'village_name'                   => 'قرية ب',
            'floor_count'                    => 5,
            'floor_area'                     => 900,
            'length_lm'                      => 20,
            'actual_exploration_point_count' => 5,
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
        ]);
        $this->uploadPdf($draft->id, 'survey_contract');
        $this->uploadPdf($draft->id, 'site_investigation_report');
        $this->uploadPdf($draft->id, 'previous_office_clearance');
        $this->uploadPdf($draft->id, 'previous_office_discharge');

        $this->postJson("/api/v1/applications/{$draft->id}/submit")
            ->assertOk()
            ->assertJsonPath('application.status', 'submitted');
    }

    // ── helpers ─────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function payloadWithOwner(string $owner): array
    {
        return [
            'basin_number'           => '007',
            'basin_or_location_name' => 'حوض التجربة',
            'parcel_number'          => '042',
            'contract_owner_name'    => $owner,
        ];
    }

    private function makeApplicant(Organization $org, string $email): User
    {
        return User::create([
            'organization_id' => $org->id, 'name' => 'office', 'email' => $email,
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $data */
    private function makeDraft(ServiceDefinition $svc, Organization $org, User $applicant, array $data): Application
    {
        return Application::create([
            'reference_number'      => Application::generateReference($svc),
            'organization_id'       => $org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);
    }

    /** @param array<string,mixed> $data */
    private function makeSubmittedApp(ServiceDefinition $svc, Organization $org, User $applicant, array $data): Application
    {
        $app = $this->makeDraft($svc, $org, $applicant, $data);
        $app->update(['status' => Application::STATUS_SUBMITTED]);
        return $app;
    }

    private function attachDocument(Application $app, string $docId): void
    {
        ApplicationDocument::create([
            'application_id'    => $app->id,
            'document_id'       => $docId,
            'original_filename' => "{$docId}.pdf",
            'stored_filename'   => "test-{$docId}.pdf",
            'disk'              => 'local',
            'path'              => "uploads/test/{$docId}.pdf",
            'mime_type'         => 'application/pdf',
            'size_bytes'        => 1024,
            'uploaded_by'       => $app->applicant_id,
        ]);
    }

    /** @param array<string,mixed> $data */
    private function createDraftViaApi(array $data): Application
    {
        $r = $this->postJson('/api/v1/applications', [
            'service_code' => 'SRV-001',
            'data'         => $data,
        ])->assertStatus(201)->json('application');
        return Application::findOrFail($r['id']);
    }

    private function uploadPdf(int $appId, string $docId): void
    {
        $this->postJson("/api/v1/applications/{$appId}/documents", [
            'document_id' => $docId,
            'file'        => UploadedFile::fake()->createWithContent(
                "{$docId}.pdf",
                "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n",
            ),
        ])->assertStatus(201);
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
