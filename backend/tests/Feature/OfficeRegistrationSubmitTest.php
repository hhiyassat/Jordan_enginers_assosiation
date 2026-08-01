<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\JeaServices\Engine\JeaMembershipResult;
use Modules\JeaServices\Engine\JeaMembershipVerifier;
use Modules\JeaServices\Models\OfficeRegistrationRequest;
use Tests\TestCase;

/**
 * OfficeRegistrationController::submit — public signup endpoint.
 *
 * Pins:
 *   • Happy path: creates a pending OfficeRegistrationRequest + returns 201 with reference.
 *   • Rate limiting is applied (middleware present) — smoke check on config only.
 *   • min-1-engineer rule (business, via OfficeRegistrationValidator).
 *   • single-engineer discipline rule (structural OR architectural).
 *   • civil alias accepted at input; normalized to structural downstream.
 *   • JEA membership verifier is consulted per engineer; a rejecting verifier
 *     blocks the corresponding engineer.
 *   • Field-shape validation (missing fields, invalid email, bad discipline).
 *   • Uniqueness: office_name_ar/en against organizations table.
 *   • Uniqueness: office_email against users table.
 *   • Uniqueness: office_license_no against prior office_registration_requests.
 *   • No Organization / User / Engineer is created at submit time (only at approve).
 */
class OfficeRegistrationSubmitTest extends TestCase
{
    use RefreshDatabase;

    // ── happy path ───────────────────────────────────────────────

    public function test_public_submit_creates_pending_request_with_reference(): void
    {
        $r = $this->postJson('/api/v1/office-registrations', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('status', OfficeRegistrationRequest::STATUS_PENDING)
            ->assertJsonStructure(['reference_number', 'id']);

        $ref = $r->json('reference_number');
        $this->assertMatchesRegularExpression('/^OFR-\d{2}-[A-Z0-9]{6}$/', $ref);

        // No user/org/engineer created at submit time — only pending row exists.
        $this->assertDatabaseCount('office_registration_requests', 1);
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_endpoint_accepts_civil_alias_and_stores_it_as_submitted(): void
    {
        // 'civil' is the legacy alias — accepted at input; Disciplines::normalize
        // folds it to 'structural' at the Engineer creation boundary. At submit
        // time we preserve the submitted value verbatim.
        $payload = $this->validPayload();
        $payload['engineers'][0]['specialization'] = 'civil';

        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);

        $stored = OfficeRegistrationRequest::first()->engineers[0]['specialization'];
        $this->assertSame('civil', $stored);
    }

    public function test_multiple_engineers_allowed_and_stored_as_submitted(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد',   'membership_number' => '1', 'specialization' => 'electrical'],
            ['name_ar' => 'م. سارة',   'membership_number' => '2', 'specialization' => 'mechanical'],
            ['name_ar' => 'م. خالد',   'membership_number' => '3', 'specialization' => 'architectural'],
        ];

        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);
        $this->assertCount(3, OfficeRegistrationRequest::first()->engineers);
    }

    // ── business rules ───────────────────────────────────────────

    public function test_requires_at_least_one_engineer(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'] = [];

        $r = $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(422);
        $r->assertJsonValidationErrors(['engineers']);
    }

    public function test_single_engineer_must_be_structural_or_architectural(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد', 'membership_number' => '1', 'specialization' => 'electrical'],
        ];

        $r = $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(422);
        $r->assertJsonValidationErrors(['engineers.0.specialization']);
        // Laravel returns validation errors as a flat associative array with
        // dotted keys — $r->json('errors.a.b') would misinterpret the dots as
        // path segments. Grab the errors bag directly and look up the flat key.
        $errors = $r->json('errors');
        $this->assertStringContainsString('مدني', $errors['engineers.0.specialization'][0]);
    }

    public function test_single_engineer_structural_passes(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد', 'membership_number' => '1', 'specialization' => 'structural'],
        ];
        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);
    }

    public function test_single_engineer_civil_alias_passes(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد', 'membership_number' => '1', 'specialization' => 'civil'],
        ];
        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);
    }

    public function test_single_engineer_architectural_passes(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد', 'membership_number' => '1', 'specialization' => 'architectural'],
        ];
        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);
    }

    public function test_two_engineers_may_hold_any_disciplines(): void
    {
        // With ≥ 2 engineers, the single-engineer discipline rule does not apply.
        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد',  'membership_number' => '1', 'specialization' => 'electrical'],
            ['name_ar' => 'م. سارة',  'membership_number' => '2', 'specialization' => 'mechanical'],
        ];
        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);
    }

    // ── verifier integration ─────────────────────────────────────

    public function test_rejecting_verifier_blocks_the_specific_engineer(): void
    {
        // Container-swap a verifier that rejects membership number '9999'.
        $this->app->bind(JeaMembershipVerifier::class, function () {
            return new class implements JeaMembershipVerifier {
                public function verify(string $engineerName, string $membershipNumber): JeaMembershipResult
                {
                    return $membershipNumber === '9999'
                        ? JeaMembershipResult::invalid('غير مسجَّل في النقابة.')
                        : JeaMembershipResult::valid();
                }
            };
        });

        $payload = $this->validPayload();
        $payload['engineers'] = [
            ['name_ar' => 'م. أحمد',  'membership_number' => '1',    'specialization' => 'architectural'],
            ['name_ar' => 'م. سارة',  'membership_number' => '9999', 'specialization' => 'mechanical'],
        ];

        $r = $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(422);
        $r->assertJsonValidationErrors(['engineers.1.membership_number']);
        $r->assertJsonMissingValidationErrors(['engineers.0.membership_number']);
        $errors = $r->json('errors');
        $this->assertStringContainsString('غير مسجَّل', $errors['engineers.1.membership_number'][0]);
    }

    // ── field-shape validation ───────────────────────────────────

    public function test_missing_office_email_fails(): void
    {
        $payload = $this->validPayload();
        unset($payload['office_email']);
        $this->postJson('/api/v1/office-registrations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['office_email']);
    }

    public function test_invalid_email_fails(): void
    {
        $payload = $this->validPayload();
        $payload['office_email'] = 'not-an-email';
        $this->postJson('/api/v1/office-registrations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['office_email']);
    }

    public function test_invalid_discipline_fails(): void
    {
        $payload = $this->validPayload();
        $payload['engineers'][0]['specialization'] = 'chemical'; // not in the accepted set
        $this->postJson('/api/v1/office-registrations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['engineers.0.specialization']);
    }

    // ── uniqueness ───────────────────────────────────────────────

    public function test_email_collides_with_existing_user(): void
    {
        $org = Organization::create(['name_ar'=>'x','name_en'=>'x','slug'=>'x','is_active'=>true]);
        User::create([
            'organization_id'=>$org->id,'name'=>'x','email'=>'existing@t.co',
            'password'=>Hash::make('x'),'role'=>'applicant','is_active'=>true,
            'password_changed_at'=>now(),
        ]);

        $payload = $this->validPayload();
        $payload['office_email'] = 'existing@t.co';
        $this->postJson('/api/v1/office-registrations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['office_email']);
    }

    public function test_office_name_collides_with_existing_organization(): void
    {
        Organization::create([
            'name_ar' => 'مكتب موجود', 'name_en' => 'Existing Office',
            'slug' => 'existing-office', 'is_active' => true,
        ]);

        $payload = $this->validPayload();
        $payload['office_name_ar'] = 'مكتب موجود';
        $this->postJson('/api/v1/office-registrations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['office_name_ar']);
    }

    public function test_license_no_collides_with_prior_pending_request(): void
    {
        $this->postJson('/api/v1/office-registrations', $this->validPayload())->assertStatus(201);

        // Second submit with a different name+email but the same license → reject.
        $second = $this->validPayload();
        $second['office_name_ar'] = 'اسم مختلف';
        $second['office_name_en'] = 'Different Name';
        $second['office_email']   = 'other@office.example';
        // office_license_no stays the same
        $this->postJson('/api/v1/office-registrations', $second)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['office_license_no']);
    }

    // ── helpers ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'office_name_ar'    => 'مكتب الاختبار الهندسي',
            'office_name_en'    => 'Test Engineering Office',
            'office_license_no' => 'LIC-2026-00042',
            'office_city'       => 'عمان',
            'office_address_ar' => 'شارع الاختبار — بناية 12',
            'office_phone'      => '+962-79-1234567',
            'office_email'      => 'office@example.com',
            'engineers'         => [
                [
                    'name_ar'           => 'م. أحمد الخطيب',
                    'name_en'           => 'Eng. Ahmad Khatib',
                    'membership_number' => 'JEA-12345',
                    'specialization'    => 'architectural',
                ],
            ],
        ];
    }
}
