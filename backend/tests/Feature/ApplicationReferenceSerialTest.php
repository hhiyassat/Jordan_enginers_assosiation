<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationCounter;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * H-02: Application reference-number allocation is atomic.
 *
 * The prior generator did `Application::count() + 1` inside
 * generateReference(). Two concurrent submits for the same
 * (service, year) both saw N and both wrote N+1 — one insert
 * died on the reference_number unique index.
 *
 * These tests pin the atomic-counter semantics; cross-process
 * concurrency verification is gated on PostgreSQL CI (blocked
 * externally by C-05).
 */
class ApplicationReferenceSerialTest extends TestCase
{
    use RefreshDatabase;

    private ServiceDefinition $service;
    private Organization $org;
    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-ref', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'a',
            'email' => 'a-ref@t.esp', 'password' => Hash::make('x'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'            => 'REF-TEST',
            'name_ar'         => 'خدمة',
            'name_en'         => 'Service',
            'currency'        => 'JOD',
            'status'          => 'active',
            'is_locked'       => false,
            'schema'          => ['workflow' => ['stages' => []], 'fields' => []],
        ]);
    }

    public function test_consecutive_generate_reference_calls_produce_strictly_increasing_sequence(): void
    {
        $refs = [];
        for ($i = 0; $i < 4; $i++) {
            $refs[] = Application::generateReference($this->service);
        }

        // Format: YY (2) + SVC (4, service.id % 10000) + SEQ (4)
        // We assert only the SEQ tail — YY and SVC are stable within the test.
        $seqs = array_map(fn ($r) => (int) substr($r, -4), $refs);
        $this->assertSame([1, 2, 3, 4], $seqs,
            'H-02: reference sequence must be strictly monotonic — no duplicates, no gaps.');
    }

    public function test_generate_reference_respects_pre_existing_counter_row(): void
    {
        // Simulate a concurrent winner: counter row already exists with
        // next_serial=42.
        ApplicationCounter::create([
            'service_definition_id' => $this->service->id,
            'year'                  => (int) now()->format('Y'),
            'next_serial'           => 42,
        ]);

        $ref = Application::generateReference($this->service);

        $this->assertSame(42, (int) substr($ref, -4),
            'H-02: allocation must respect a pre-existing counter row.');
        $this->assertSame(43,
            ApplicationCounter::where('service_definition_id', $this->service->id)
                ->where('year', now()->year)->value('next_serial'),
            'Counter must advance after allocation.');
    }

    public function test_reference_uniqueness_is_preserved_across_many_allocations(): void
    {
        $seen = [];
        for ($i = 0; $i < 20; $i++) {
            $ref     = Application::generateReference($this->service);
            $seen[]  = $ref;
        }
        $this->assertSame(count($seen), count(array_unique($seen)),
            'H-02: no two references may coincide.');
    }

    public function test_two_services_get_independent_counters(): void
    {
        $otherService = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'            => 'REF-TEST-2',
            'name_ar'         => 'خدمة أخرى',
            'name_en'         => 'Other',
            'currency'        => 'JOD',
            'status'          => 'active',
            'is_locked'       => false,
            'schema'          => ['workflow' => ['stages' => []], 'fields' => []],
        ]);

        Application::generateReference($this->service);      // service=1: allocates 1
        Application::generateReference($this->service);      // service=1: allocates 2
        $refOther = Application::generateReference($otherService); // service=2: allocates 1

        $this->assertSame(1, (int) substr($refOther, -4),
            'H-02: each service has an independent counter.');
    }
}
