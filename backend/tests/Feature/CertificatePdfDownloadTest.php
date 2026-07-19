<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Engine\WorkflowEngine;
use App\Models\Application;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 4: public certificate PDF download.
 *
 * The endpoint is public but token-gated. Applicants + third parties
 * pick up the token from the signed URL (which the application-detail
 * endpoint returns) or from the printed QR. Timing-safe comparison
 * keeps the response time uniform for unknown vs. known cert numbers.
 */
class CertificatePdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function issuedCert(): array
    {
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $applicant = User::create([
            'organization_id' => $org->id, 'name' => 'أحمد', 'email' => 'a@t.esp',
            'password' => Hash::make('x'), 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $issuer = User::create([
            'organization_id' => $org->id, 'name' => 'staff', 'email' => 's@t.esp',
            'password' => Hash::make('x'), 'role' => 'staff', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $service = ServiceDefinition::create([
            'organization_id' => $org->id, 'code' => 'CERT-PDF',
            'name_ar' => 'شهادة الاختبار', 'name_en' => 'Test Certificate',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'review', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
                ]],
                'certificate' => [
                    'validity_months' => 12,
                    'title_ar'        => 'شهادة إتمام',
                    'title_en'        => 'Certificate of Completion',
                    'fields_on_cert'  => ['owner_name'],
                ],
                'fields' => [
                    ['id' => 'owner_name', 'label_ar' => 'اسم المالك', 'label_en' => 'Owner', 'type' => 'text'],
                ],
                'documents' => [], 'sections' => [],
            ],
        ]);
        $app = Application::create([
            'reference_number' => 'A-PDF-1', 'organization_id' => $org->id,
            'service_definition_id' => $service->id, 'applicant_id' => $applicant->id,
            'status' => Application::STATUS_APPROVED, 'current_stage' => 'review',
            'data' => ['owner_name' => 'حسين'], 'fee_amount' => 0, 'payment_status' => 'waived',
        ]);
        $cert = (new WorkflowEngine($service))->issueCertificate($app, $issuer);
        return compact('cert', 'app', 'service', 'applicant');
    }

    public function test_endpoint_streams_a_pdf_when_the_token_matches(): void
    {
        ['cert' => $cert] = $this->issuedCert();

        $r = $this->get("/api/v1/certificates/{$cert->certificate_number}/pdf?token={$cert->qr_token}");
        $r->assertOk();
        $r->assertHeader('Content-Type', 'application/pdf');
        // PDF magic bytes so we know dompdf actually produced a real PDF
        // and not an HTML error page rendered with the wrong content type.
        $body = $r->getContent();
        $this->assertStringStartsWith('%PDF-', $body, 'Response body must start with the PDF signature');
        // Content streams inside a dompdf PDF are FlateDecode-compressed,
        // so string-searching for the cert number in the raw bytes won't
        // work. Size threshold is the next-best sanity: an empty template
        // would produce ~2KB, our real cert with QR runs 8KB+.
        $this->assertGreaterThan(5000, strlen($body), 'PDF should carry real content, not an empty template');
    }

    public function test_returns_404_when_the_certificate_number_is_unknown(): void
    {
        $this->get('/api/v1/certificates/CERT-DOES-NOT-EXIST/pdf?token=' . str_repeat('a', 64))
            ->assertNotFound();
    }

    public function test_returns_404_when_the_token_is_wrong_even_if_cert_exists(): void
    {
        ['cert' => $cert] = $this->issuedCert();
        // Same length as a real token so timing-safe compare has something
        // to compare against — the important thing is the 404 path fires.
        $this->get("/api/v1/certificates/{$cert->certificate_number}/pdf?token=" . str_repeat('b', 64))
            ->assertNotFound();
    }

    public function test_returns_410_when_the_certificate_was_revoked(): void
    {
        ['cert' => $cert] = $this->issuedCert();
        $cert->update(['status' => 'revoked']);

        $r = $this->get("/api/v1/certificates/{$cert->certificate_number}/pdf?token={$cert->qr_token}");
        $r->assertStatus(410);
    }

    public function test_application_show_endpoint_returns_the_signed_pdf_url(): void
    {
        // Regression pin: without certificate_pdf_url on the show
        // response, the applicant frontend has no way to render a
        // download button — the token stays server-side.
        ['cert' => $cert, 'app' => $app, 'applicant' => $applicant] = $this->issuedCert();
        \Laravel\Sanctum\Sanctum::actingAs($applicant);

        $r = $this->getJson("/api/v1/applications/{$app->id}");
        $r->assertOk();
        $url = $r->json('certificate_pdf_url');
        $this->assertIsString($url);
        $this->assertStringContainsString($cert->certificate_number, $url);
        $this->assertStringContainsString('token=' . $cert->qr_token, $url);
    }
}
