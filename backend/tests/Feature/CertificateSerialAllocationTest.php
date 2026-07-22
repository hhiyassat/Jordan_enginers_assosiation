<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Models\Application;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * JORD-2: certificate-number serial allocation is atomic. The prior
 * "Certificate::count() + 1" pattern would allocate the same serial to
 * two concurrent issuers. This test issues three certificates in a row
 * for the same org+year and asserts strictly monotonic serials —
 * failure to advance would surface here as a duplicate insert on the
 * certificate_number unique index.
 */
class CertificateSerialAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consecutive_issues_produce_strictly_increasing_serials(): void
    {
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $applicant = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => Hash::make('x'), 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $issuer = User::create([
            'organization_id' => $org->id, 'name' => 'i', 'email' => 'i@t.esp',
            'password' => Hash::make('x'), 'role' => 'staff', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $service = ServiceDefinition::create([
            'organization_id' => $org->id, 'code' => 'CERT',
            'name_ar' => 'شهادة', 'name_en' => 'Cert',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'r', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
                ]],
                'certificate' => ['validity_months' => 12, 'title_ar' => '.', 'title_en' => '.', 'fields_on_cert' => []],
            ],
        ]);

        $engine = new WorkflowEngine($service);
        $serials = [];
        for ($i = 0; $i < 3; $i++) {
            $app = Application::create([
                'reference_number' => "A-{$i}", 'organization_id' => $org->id,
                'service_definition_id' => $service->id, 'applicant_id' => $applicant->id,
                'status' => Application::STATUS_APPROVED, 'current_stage' => 'r',
                'data' => [], 'fee_amount' => 0, 'payment_status' => 'waived',
            ]);
            $cert = $engine->issueCertificate($app, $issuer);
            $serials[] = (int) substr($cert->certificate_number, -5);
        }

        $this->assertSame([1, 2, 3], $serials,
            'Each issue must consume the next serial atomically — no duplicates, no gaps');
    }
}
