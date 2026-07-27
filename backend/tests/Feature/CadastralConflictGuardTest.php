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
use Modules\JeaServices\Engine\CadastralConflictGuard;
use Modules\JeaServices\Engine\CrossCuttingSubmissionPipeline;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * CadastralConflictGuard — STK-2026-07-27-CC-001 platform-wide gate.
 *
 * Pins:
 *   • Same-org prior application does NOT block a new same-org submission.
 *   • Different-org active application DOES block on triple match.
 *   • Different-org draft or rejected DOES NOT block.
 *   • Services without cadastral fields (CERT-*, DEC-*, etc.) pass through.
 *   • Arabic normalization: cosmetic differences don't defeat matching.
 *   • End-to-end via POST /submit — 422 + message when conflict.
 *   • Pipeline registration in the container includes CadastralConflictGuard.
 */
class CadastralConflictGuardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $officeA;
    private User $officeB;
    private ServiceDefinition $srv001;      // orgA copy — seeded via slug='demo'
    private ServiceDefinition $srv001OrgB;  // orgB copy — replicated in setUp

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

        // Seeders wire SRV-001 for slug='demo' (orgA). For orgB we clone
        // the pilot schema so both orgs have SRV-001 available and the
        // cross-org conflict can be simulated.
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new SiteSurveyFeesSeeder());
        $this->runSilently(new Srv001PilotSeeder());

        $this->srv001 = ServiceDefinition::where('organization_id', $this->orgA->id)
            ->where('code', 'SRV-001')
            ->firstOrFail();

        // Note: service_definitions.code has a UNIQUE constraint that is
        // not org-scoped (multi-tenancy artifact). Clone with a suffixed
        // code so both orgs have a cadastral-scoped service to file against.
        // The guard does not check service_code, only cadastral-field
        // presence in schema — so the code suffix is a test-only cosmetic.
        $this->srv001OrgB = $this->srv001->replicate();
        $this->srv001OrgB->organization_id = $this->orgB->id;
        $this->srv001OrgB->code = 'SRV-001-B';
        $this->srv001OrgB->save();

        Storage::fake(config('filesystems.default'));
    }

    // ── unit-level guard behavior ─────────────────────────────────────

    public function test_out_of_scope_when_service_lacks_cadastral_fields(): void
    {
        $svc = ServiceDefinition::create([
            'organization_id' => $this->orgA->id,
            'code' => 'CERT-TEST',
            'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD',
            'schema' => ['fields' => [
                ['id' => 'some_other_field', 'type' => 'text', 'required' => true, 'label_ar' => 'x'],
            ]],
        ]);
        $app = $this->makeDraft($svc, $this->orgA, $this->officeA, [
            'some_other_field' => 'value',
        ]);

        $this->assertSame([], (new CadastralConflictGuard())->validate($app));
    }

    public function test_pass_when_no_prior_application(): void
    {
        $app = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->cadastralPayload());
        $this->assertSame([], (new CadastralConflictGuard())->validate($app));
    }

    public function test_pass_when_same_org_has_prior_submitted_application(): void
    {
        // orgA files first application → submits
        $this->makeSubmittedApp($this->srv001, $this->orgA, $this->officeA, $this->cadastralPayload());
        // orgA files a second application for the same triple → cross-cutting guard must pass
        $second = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->cadastralPayload());
        $this->assertSame([], (new CadastralConflictGuard())->validate($second));
    }

    public function test_reject_when_different_org_has_submitted_application(): void
    {
        // orgB has a SUBMITTED application on the triple
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->cadastralPayload());

        // orgA tries to submit on the same triple → guard blocks
        $orgADraft = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->cadastralPayload());
        $errors = (new CadastralConflictGuard())->validate($orgADraft);
        $this->assertArrayHasKey('basin_number', $errors);
        $this->assertStringContainsString('مسجَّلان سابقاً لمكتب هندسي آخر', $errors['basin_number']);
    }

    public function test_pass_when_different_org_has_only_a_draft(): void
    {
        $this->makeDraft($this->srv001OrgB, $this->orgB, $this->officeB, $this->cadastralPayload());

        $orgADraft = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->cadastralPayload());
        $this->assertSame([], (new CadastralConflictGuard())->validate($orgADraft));
    }

    public function test_pass_when_different_org_has_only_rejected(): void
    {
        $rejected = $this->makeDraft($this->srv001OrgB, $this->orgB, $this->officeB, $this->cadastralPayload());
        $rejected->update(['status' => Application::STATUS_REJECTED]);

        $orgADraft = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, $this->cadastralPayload());
        $this->assertSame([], (new CadastralConflictGuard())->validate($orgADraft));
    }

    public function test_normalization_matches_despite_alef_variants(): void
    {
        // orgB's application uses alef-with-hamza-below
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, array_merge(
            $this->cadastralPayload(),
            ['basin_or_location_name' => 'حوض إربد'],
        ));

        // orgA submits with plain alef → guard must still detect the conflict
        $orgADraft = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, array_merge(
            $this->cadastralPayload(),
            ['basin_or_location_name' => 'حوض اربد'],
        ));
        $errors = (new CadastralConflictGuard())->validate($orgADraft);
        $this->assertArrayHasKey('basin_number', $errors);
    }

    public function test_normalization_matches_despite_whitespace_differences(): void
    {
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, array_merge(
            $this->cadastralPayload(),
            ['basin_or_location_name' => '  حوض   التجربة  '],
        ));

        $orgADraft = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, array_merge(
            $this->cadastralPayload(),
            ['basin_or_location_name' => 'حوض التجربة'],
        ));
        $this->assertArrayHasKey('basin_number', (new CadastralConflictGuard())->validate($orgADraft));
    }

    public function test_different_parcel_but_same_basin_does_not_conflict(): void
    {
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->cadastralPayload());

        // Same basin, different parcel → not a conflict
        $orgADraft = $this->makeDraft($this->srv001, $this->orgA, $this->officeA, array_merge(
            $this->cadastralPayload(),
            ['parcel_number' => '999'],
        ));
        $this->assertSame([], (new CadastralConflictGuard())->validate($orgADraft));
    }

    // ── pipeline registration ────────────────────────────────────────

    public function test_container_pipeline_includes_the_guard(): void
    {
        $pipeline = app(CrossCuttingSubmissionPipeline::class);
        $this->assertContains(
            CadastralConflictGuard::class,
            $pipeline->registeredGuards(),
            'JeaServicesServiceProvider must register CadastralConflictGuard in the cross-cutting pipeline',
        );
    }

    // ── end-to-end HTTP integration ──────────────────────────────────

    public function test_submit_endpoint_rejects_with_422_when_cadastral_conflicts_across_orgs(): void
    {
        // orgB has a committed submission on the triple
        $this->makeSubmittedApp($this->srv001OrgB, $this->orgB, $this->officeB, $this->cadastralPayload());

        // orgA prepares a full valid application on the same triple
        Sanctum::actingAs($this->officeA);
        $draft = $this->createDraftViaApi($this->cadastralPayload() + [
            'project_sector'       => 'خاص',
            'governorate'          => 'amman',
            'directorate_name'     => 'مديرية أ',
            'village_name'         => 'قرية ب',
            'contract_owner_name'  => 'شركة اختبار',
            'floor_count'          => 5,
            'floor_area'           => 900,
            'length_lm'            => 20,
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

        // Submit — cross-cutting pipeline must reject BEFORE service-specific guard
        $r = $this->postJson("/api/v1/applications/{$draft->id}/submit")
            ->assertStatus(422)
            ->json();
        $this->assertArrayHasKey('basin_number', $r['errors']);
        $this->assertStringContainsString('مسجَّلان سابقاً لمكتب هندسي آخر', $r['errors']['basin_number']);
    }

    // ── helpers ─────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function cadastralPayload(): array
    {
        return [
            'basin_number'           => '007',
            'basin_or_location_name' => 'حوض التجربة',
            'parcel_number'          => '042',
        ];
    }

    private function makeApplicant(Organization $org, string $email): User
    {
        return User::create([
            'organization_id'    => $org->id,
            'name'  => 'office',
            'email' => $email,
            'password' => 'x',
            'role' => 'applicant',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $data */
    private function makeDraft(
        ServiceDefinition $svc,
        Organization $org,
        User $applicant,
        array $data,
    ): Application {
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
    private function makeSubmittedApp(
        ServiceDefinition $svc,
        Organization $org,
        User $applicant,
        array $data,
    ): Application {
        $app = $this->makeDraft($svc, $org, $applicant, $data);
        $app->update(['status' => Application::STATUS_SUBMITTED]);
        return $app;
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
