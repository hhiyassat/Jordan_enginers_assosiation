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
 * JORD-7: array_flip($certFields) crashes if fields_on_cert contains
 * anything other than string/int keys. The fix filters to non-empty
 * strings before array_flip. This test seeds a badly-authored schema
 * and asserts issueCertificate builds the cert without warnings and
 * only carries the string-typed fields onto the certificate.
 */
class CertificateFieldsFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_string_entries_in_fields_on_cert_are_dropped_without_warning(): void
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
            'organization_id' => $org->id, 'name' => 'iss', 'email' => 'iss@t.esp',
            'password' => Hash::make('x'), 'role' => 'staff', 'is_active' => true,
            'password_changed_at' => now(),
        ]);

        // fields_on_cert mixes strings, an array, an int, an empty string,
        // and a null — all of which array_flip used to fail on. Only the
        // strings 'owner_name' and 'contract_no' should survive filtering.
        $service = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code' => 'CERT-FILTER', 'name_ar' => 'شهادة اختبار', 'name_en' => 'Test',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'review', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
                ]],
                'certificate' => [
                    'validity_months' => 12,
                    'title_ar' => 'شهادة',
                    'title_en' => 'Certificate',
                    'fields_on_cert' => [
                        'owner_name',
                        ['nested', 'array'],
                        42,
                        '',
                        null,
                        'contract_no',
                    ],
                ],
                'fields' => [], 'documents' => [], 'sections' => [],
            ],
        ]);

        $app = Application::create([
            'reference_number' => 'A-CERT-1', 'organization_id' => $org->id,
            'service_definition_id' => $service->id, 'applicant_id' => $applicant->id,
            'status' => Application::STATUS_APPROVED,
            'current_stage' => 'review',
            'data' => ['owner_name' => 'حسين', 'contract_no' => 'C-100', 'extra' => 'ignored'],
            'fee_amount' => 0, 'payment_status' => 'waived',
        ]);

        $engine = new WorkflowEngine($service);
        $cert = $engine->issueCertificate($app, $issuer);

        $this->assertNotNull($cert);
        // certificate_data reflects only the string fields, and each of
        // those pulled the matching value out of $app->data.
        $this->assertSame([
            'owner_name'  => 'حسين',
            'contract_no' => 'C-100',
        ], $cert->cert_data);
    }
}
