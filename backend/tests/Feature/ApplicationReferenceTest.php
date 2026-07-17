<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-008: Application::generateReference must emit a 10-digit
 * {YY}{ServiceCode:4}{Seq:4} reference number. These tests lock the
 * format so a future refactor can't silently break it.
 */
class ApplicationReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar'   => 'منظمة اختبار',
            'name_en'   => 'Test Org',
            'slug'      => 'test-org',
            'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'Applicant',
            'email'               => 'app@test.dev',
            'password'            => 'x',
            'role'                => 'applicant',
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);
    }

    public function test_reference_is_exactly_10_digits(): void
    {
        $service = $this->makeService('SVC-A');
        $ref = Application::generateReference($service);

        $this->assertMatchesRegularExpression('/^\d{10}$/', $ref);
    }

    public function test_reference_leads_with_two_digit_year(): void
    {
        $service = $this->makeService('SVC-B');
        $ref = Application::generateReference($service);

        $expectedYy = str_pad((string) (now()->year % 100), 2, '0', STR_PAD_LEFT);
        $this->assertSame($expectedYy, substr($ref, 0, 2));
    }

    public function test_reference_encodes_service_id_padded_to_4_digits(): void
    {
        $service = $this->makeService('SVC-C');
        $ref = Application::generateReference($service);

        $expectedCode = str_pad((string) ($service->id % 10000), 4, '0', STR_PAD_LEFT);
        $this->assertSame($expectedCode, substr($ref, 2, 4));
    }

    public function test_sequence_starts_at_0001_for_first_of_year(): void
    {
        $service = $this->makeService('SVC-D');
        $ref = Application::generateReference($service);

        $this->assertSame('0001', substr($ref, 6, 4));
    }

    public function test_sequence_increments_per_service_per_year(): void
    {
        $service = $this->makeService('SVC-E');

        // Insert a prior application for this service in the current year.
        $this->createApp($service, 'first');

        $ref = Application::generateReference($service);
        $this->assertSame('0002', substr($ref, 6, 4));
    }

    public function test_sequence_is_independent_across_services(): void
    {
        $a = $this->makeService('SVC-F');
        $b = $this->makeService('SVC-G');

        $this->createApp($a, 'a1');
        $this->createApp($a, 'a2');
        // Service B has no prior apps → its next seq should be 0001.
        $this->assertSame('0001', substr(Application::generateReference($b), 6, 4));
        // Service A should be at 0003.
        $this->assertSame('0003', substr(Application::generateReference($a), 6, 4));
    }

    public function test_prior_year_applications_do_not_count_toward_sequence(): void
    {
        $service = $this->makeService('SVC-H');

        // Create an app dated last year — should not consume a slot in the
        // current-year sequence.
        $app = $this->createApp($service, 'legacy');
        $app->created_at = now()->subYear()->startOfYear();
        $app->save();

        $ref = Application::generateReference($service);
        $this->assertSame('0001', substr($ref, 6, 4));
    }

    private function makeService(string $code): ServiceDefinition
    {
        return ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'            => $code,
            'name_ar'         => "خدمة {$code}",
            'name_en'         => "Service {$code}",
            'currency'        => 'JOD',
            'status'          => 'active',
            'schema'          => [
                'workflow'    => ['stages' => [['id' => 's', 'label_ar' => 'x', 'label_en' => 'x', 'role' => 'staff', 'sla_hours' => 24, 'actions' => ['approve']]]],
                'fee'         => ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD'],
                'fields'      => [],
                'documents'   => [],
                'certificate' => ['validity_months' => 0, 'title_ar' => 'x', 'title_en' => 'x', 'fields_on_cert' => []],
            ],
        ]);
    }

    private function createApp(ServiceDefinition $service, string $tag): Application
    {
        return Application::create([
            'reference_number'      => "SEED-{$tag}-" . uniqid(),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);
    }
}
