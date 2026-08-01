<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * P1-10 · Authenticated certificate PDF download.
 *
 * Complements the public token URL. The applicant, staff, auditor, and
 * admin can fetch their own certificate PDF without any token in the
 * URL — org scope + applicant-own-only for applicants — so the qr_token
 * never lands in browser history / referrer / upstream logs.
 */
class CertificateAuthenticatedDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $applicantA;
    private User $applicantB;
    private User $staffA;
    private Application $appA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::create(['name_ar' => 'a', 'name_en' => 'a', 'slug' => 'cert-a', 'is_active' => true]);
        $this->orgB = Organization::create(['name_ar' => 'b', 'name_en' => 'b', 'slug' => 'cert-b', 'is_active' => true]);
        $this->applicantA = $this->makeUser($this->orgA, 'applicant', 'cert-app-a@t.esp');
        $this->applicantB = $this->makeUser($this->orgB, 'applicant', 'cert-app-b@t.esp');
        $this->staffA     = $this->makeUser($this->orgA, 'staff',     'cert-staff-a@t.esp');
        $issuer           = $this->makeUser($this->orgA, 'staff',     'cert-issuer@t.esp');

        $service = ServiceDefinition::create([
            'organization_id' => $this->orgA->id,
            'code'    => 'CERT-P1-10', 'name_ar' => 'ش', 'name_en' => 'S',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'r', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
                ]],
                'certificate' => [
                    'validity_months' => 12,
                    'title_ar' => 'شهادة', 'title_en' => 'Cert',
                    'fields_on_cert' => [],
                ],
                'fields' => [], 'documents' => [], 'sections' => [],
            ],
        ]);
        $this->appA = Application::create([
            'reference_number' => 'CERT-P1-10', 'organization_id' => $this->orgA->id,
            'service_definition_id' => $service->id, 'applicant_id' => $this->applicantA->id,
            'status' => Application::STATUS_APPROVED, 'current_stage' => 'r',
            'data' => [], 'fee_amount' => 0, 'payment_status' => 'waived',
        ]);
        (new WorkflowEngine($service))->issueCertificate($this->appA, $issuer);
    }

    public function test_owning_applicant_can_download_without_token_in_url(): void
    {
        Sanctum::actingAs($this->applicantA);
        $r = $this->get("/api/v1/applications/{$this->appA->id}/certificate/pdf");
        $r->assertOk();
        $r->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
    }

    public function test_same_org_reviewer_can_download(): void
    {
        Sanctum::actingAs($this->staffA);
        $this->get("/api/v1/applications/{$this->appA->id}/certificate/pdf")->assertOk();
    }

    public function test_cross_org_applicant_gets_404(): void
    {
        Sanctum::actingAs($this->applicantB);
        $this->get("/api/v1/applications/{$this->appA->id}/certificate/pdf")->assertNotFound();
    }

    public function test_returns_410_when_revoked(): void
    {
        $this->appA->certificate->update(['status' => 'revoked']);
        Sanctum::actingAs($this->applicantA);
        $this->get("/api/v1/applications/{$this->appA->id}/certificate/pdf")->assertStatus(410);
    }

    public function test_returns_404_when_no_certificate_yet(): void
    {
        $this->appA->certificate->delete();
        Sanctum::actingAs($this->applicantA);
        $this->get("/api/v1/applications/{$this->appA->id}/certificate/pdf")->assertNotFound();
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
