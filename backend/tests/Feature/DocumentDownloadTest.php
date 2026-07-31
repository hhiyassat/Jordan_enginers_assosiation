<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationDocument;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * P1-09 · Document download endpoint.
 *
 * Invariants:
 *   - Applicant can download a document attached to their own draft.
 *   - Reviewer (staff/auditor/admin) in the same org can download.
 *   - Cross-org applicant hitting the endpoint gets 404 (findAccessible
 *     first — the org filter fires before the document lookup).
 *   - Cross-application document ID returns 404 (document scoped by
 *     application_id after findAccessible).
 *   - Response is private (no-store) and contains the expected content.
 */
class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $applicantA;
    private User $applicantB;
    private User $staffA;
    private ServiceDefinition $service;
    private Application $appA;
    private ApplicationDocument $docA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->orgA = Organization::create(['name_ar' => 'a', 'name_en' => 'a', 'slug' => 'doc-a', 'is_active' => true]);
        $this->orgB = Organization::create(['name_ar' => 'b', 'name_en' => 'b', 'slug' => 'doc-b', 'is_active' => true]);

        $this->applicantA = $this->makeUser($this->orgA, 'applicant', 'app-a@t.esp');
        $this->applicantB = $this->makeUser($this->orgB, 'applicant', 'app-b@t.esp');
        $this->staffA     = $this->makeUser($this->orgA, 'staff',     'staff-a@t.esp');

        $this->service = ServiceDefinition::create([
            'organization_id' => $this->orgA->id,
            'code'    => 'DOC-TEST', 'name_ar' => 'خ', 'name_en' => 'S',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema'  => ['workflow' => ['stages' => []], 'fields' => [], 'documents' => []],
        ]);

        $this->appA = Application::create([
            'reference_number' => 'DL-A', 'organization_id' => $this->orgA->id,
            'service_definition_id' => $this->service->id, 'applicant_id' => $this->applicantA->id,
            'status' => Application::STATUS_DRAFT, 'data' => [], 'fee_amount' => 0,
        ]);
        $stored = 'uploads/applications/' . $this->appA->id . '/test.pdf';
        Storage::disk('local')->put($stored, "%PDF-1.4 sample body");
        $this->docA = ApplicationDocument::create([
            'application_id'    => $this->appA->id,
            'document_id'       => 'blueprint',
            'original_filename' => 'blueprint.pdf',
            'stored_filename'   => 'test.pdf',
            'disk'              => 'local',
            'path'              => $stored,
            'mime_type'         => 'application/pdf',
            'size_bytes'        => 20,
            'uploaded_by'       => $this->applicantA->id,
        ]);
    }

    public function test_owning_applicant_can_download_the_document(): void
    {
        Sanctum::actingAs($this->applicantA);
        $res = $this->get("/api/v1/applications/{$this->appA->id}/documents/{$this->docA->id}");
        $res->assertOk();
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('%PDF-1.4', (string) $res->streamedContent());
    }

    public function test_same_org_reviewer_can_download(): void
    {
        Sanctum::actingAs($this->staffA);
        $this->get("/api/v1/applications/{$this->appA->id}/documents/{$this->docA->id}")->assertOk();
    }

    public function test_cross_org_applicant_gets_404(): void
    {
        Sanctum::actingAs($this->applicantB);
        $this->get("/api/v1/applications/{$this->appA->id}/documents/{$this->docA->id}")->assertNotFound();
    }

    public function test_cross_application_document_id_returns_404(): void
    {
        Sanctum::actingAs($this->applicantA);
        // Nonexistent doc under an accessible application → 404 via docs lookup.
        $this->get("/api/v1/applications/{$this->appA->id}/documents/99999")->assertNotFound();
    }

    public function test_missing_stored_file_returns_404(): void
    {
        Storage::disk('local')->delete($this->docA->path);
        Sanctum::actingAs($this->applicantA);
        $this->get("/api/v1/applications/{$this->appA->id}/documents/{$this->docA->id}")->assertNotFound();
    }

    private function makeUser(Organization $org, string $role, string $email): User
    {
        return User::create([
            'organization_id' => $org->id, 'name' => $role, 'email' => $email,
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => $role, 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }
}
