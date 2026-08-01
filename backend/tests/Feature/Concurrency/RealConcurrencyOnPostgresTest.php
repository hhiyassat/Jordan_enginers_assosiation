<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationCounter;
use Modules\JeaServices\Models\Certificate;
use Modules\JeaServices\Models\CertificateCounter;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * Real cross-process concurrency verification on PostgreSQL.
 *
 * The mandate requires: "independent processes or workers, independent
 * database connections, synchronized start, same counter key or
 * cadastral identity" — verifying zero duplicates on the counter tables
 * and exactly-one-accepted for cadastral conflicts.
 *
 * This test only runs on Postgres. On sqlite it skips (pcntl_fork on
 * an in-memory sqlite has no shared state between processes).
 *
 * Uses pcntl_fork to spawn N children, each opening its own PDO
 * connection, all racing the same counter row. The parent collects
 * exit statuses + reads the DB rows to assert:
 *   - allocateReferenceSerial: N distinct serials, no duplicates
 *   - allocateCertificateSerial: same
 *   - cadastral conflict guard: 1 accepted, N-1 rejected
 */
class RealConcurrencyOnPostgresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension required');
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('real concurrency test requires PostgreSQL (pgsql driver)');
        }
        // Cannot use RefreshDatabase — that opens a transaction the
        // forked children can't see. Instead: TRUNCATE the touched
        // tables + reset sequences before each test.
        // Only runs after the driver check so sqlite runs skip cleanly.
        $this->truncateForConcurrencyTests();
    }

    protected function tearDown(): void
    {
        // Same cleanup after so subsequent test files inherit a clean
        // state. Guarded on driver in case setUp already skipped.
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->truncateForConcurrencyTests();
        }
        parent::tearDown();
    }

    private function truncateForConcurrencyTests(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // Belt-and-braces guard — sqlite doesn't support TRUNCATE.
        }
        // Order matters: children before parents.
        $tables = [
            'application_reviews', 'application_documents', 'certificates',
            'application_counters', 'certificate_counters',
            'applications', 'service_definitions',
            'personal_access_tokens', 'users', 'organizations',
        ];
        foreach ($tables as $t) {
            DB::statement("TRUNCATE TABLE \"{$t}\" RESTART IDENTITY CASCADE");
        }
    }

    public function test_application_reference_counter_zero_duplicates_under_parallel_load(): void
    {
        $this->seedFreshCatalog();
        $service = ServiceDefinition::first();

        $parallel = 8;
        $collected = $this->forkAndCollect($parallel, function () use ($service) {
            // Each child re-boots Laravel implicitly via the container we
            // captured. Force a fresh DB connection so each PID has its
            // own PDO handle.
            DB::purge();
            return Application::allocateReferenceSerial((int) $service->id, (int) now()->year);
        });

        sort($collected);
        $this->assertCount($parallel, $collected);
        $this->assertSame($collected, array_values(array_unique($collected)),
            'Application reference serials must be pairwise distinct under parallel load');
        $counter = ApplicationCounter::where('service_definition_id', $service->id)
            ->where('year', now()->year)->first();
        $this->assertSame($parallel + 1, (int) $counter->next_serial,
            'next_serial must advance exactly N times');
    }

    public function test_certificate_serial_counter_zero_duplicates_under_parallel_load(): void
    {
        $this->seedFreshCatalog();
        $service = ServiceDefinition::first();
        $applicant = User::where('role', 'applicant')->first();
        $issuer    = User::where('role', 'staff')->first();

        // Pre-create approved applications to issue certs against.
        $parallel = 6;
        $appIds = [];
        for ($i = 0; $i < $parallel; $i++) {
            $app = Application::create([
                'reference_number'      => "APP-CONC-{$i}",
                'organization_id'       => $service->organization_id,
                'service_definition_id' => $service->id,
                'applicant_id'          => $applicant->id,
                'status'                => Application::STATUS_APPROVED,
                'current_stage'         => 'review',
                'data'                  => [], 'fee_amount' => 0, 'payment_status' => 'waived',
            ]);
            $appIds[] = $app->id;
        }

        $issuerId = $issuer->id;
        $svcId    = $service->id;
        $collected = $this->forkAndCollectPerInput($appIds, function (int $appId) use ($svcId, $issuerId) {
            DB::purge();
            $svc    = ServiceDefinition::find($svcId);
            $app    = Application::find($appId);
            $issuer = User::find($issuerId);
            $cert   = (new WorkflowEngine($svc))->issueCertificate($app, $issuer);
            return (int) substr($cert->certificate_number, -5);
        });

        sort($collected);
        $this->assertCount($parallel, $collected);
        $this->assertSame($collected, array_values(array_unique($collected)),
            'Certificate serials must be pairwise distinct under parallel load');
    }

    public function test_cadastral_conflict_guard_exactly_one_accepted_under_parallel_race(): void
    {
        $this->seedFreshCatalog();
        $service = ServiceDefinition::first();

        // Two organizations, both trying to submit an application with
        // the SAME basin + parcel + owner. The cadastral cross-cutting
        // pipeline must block the second one.
        $orgA = Organization::first();
        $orgB = Organization::create(['name_ar' => 'B', 'name_en' => 'B', 'slug' => 'orgB-conc', 'is_active' => true]);

        $userA = User::where('organization_id', $orgA->id)->where('role', 'applicant')->first();
        $userB = User::create([
            'organization_id' => $orgB->id, 'name' => 'ub', 'email' => 'ub-conc@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);

        // Pre-seed two DRAFT applications, one per org, with the same
        // cadastral triple. Then in each child call submit() on its own.
        $draftA = $this->makeDraft($orgA->id, $service->id, $userA->id, ['basin_number' => '99', 'parcel_number' => '99', 'basin_or_location_name' => 'الحي', 'contract_owner_name' => 'X']);
        $draftB = $this->makeDraft($orgB->id, $service->id, $userB->id, ['basin_number' => '99', 'parcel_number' => '99', 'basin_or_location_name' => 'الحي', 'contract_owner_name' => 'Y']);

        $svcId = $service->id;
        $pairs = [[$draftA->id, $userA->id], [$draftB->id, $userB->id]];
        $collected = $this->forkAndCollectPerInput($pairs, function (array $pair) use ($svcId) {
            DB::purge();
            [$appId, $actorId] = $pair;
            $app    = Application::withoutOrgScope()->find($appId);
            $actor  = User::find($actorId);
            $svc    = ServiceDefinition::find($svcId);
            try {
                (new WorkflowEngine($svc))->submit($app, $actor);
                return 'accepted';
            } catch (\Throwable $e) {
                return 'rejected';
            }
        });

        $accepted = count(array_filter($collected, fn ($r) => $r === 'accepted'));
        $rejected = count(array_filter($collected, fn ($r) => $r === 'rejected'));

        $this->assertSame(2, count($collected));
        $this->assertSame(1, $accepted, "exactly one submission must be accepted; got {$accepted} accepted, {$rejected} rejected");
        $this->assertSame(1, $rejected);
    }

    // ── helpers ────────────────────────────────────────────────────

    /**
     * @param  callable(): mixed  $work
     * @return list<mixed>
     */
    private function forkAndCollect(int $n, callable $work): array
    {
        return $this->forkAndCollectPerInput(range(1, $n), fn () => $work());
    }

    /**
     * Fork one child per element of $inputs; each child runs
     * $work($input) and writes its return to a shared tmp file. Parent
     * reads results after wait().
     *
     * @template TIn
     * @param  list<TIn>  $inputs
     * @param  callable(TIn): mixed  $work
     * @return list<mixed>
     */
    private function forkAndCollectPerInput(array $inputs, callable $work): array
    {
        $file = tempnam(sys_get_temp_dir(), 'concurrency-');
        $pids = [];

        // Barrier file so all children start work at (approximately)
        // the same instant — simple busy-loop wait for the file to
        // exist, giving fork+bootstrap overhead time to level out.
        $barrier = tempnam(sys_get_temp_dir(), 'barrier-');
        @unlink($barrier);

        foreach ($inputs as $input) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                // child
                while (!file_exists($barrier)) usleep(1000);
                try {
                    $result = $work($input);
                } catch (\Throwable $e) {
                    $result = 'error:' . $e->getMessage();
                }
                $entry = json_encode([getmypid() => $result]) . "\n";
                file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
                exit(0);
            }
            if ($pid === -1) {
                $this->fail('pcntl_fork() failed');
            }
            $pids[] = $pid;
        }

        // Release the barrier — every child starts within a few ms.
        touch($barrier);

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        @unlink($barrier);

        $lines = array_filter(explode("\n", (string) file_get_contents($file)));
        @unlink($file);
        $results = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $results[] = array_values($decoded)[0];
            }
        }
        return $results;
    }

    private function seedFreshCatalog(): void
    {
        $org = Organization::create(['name_ar' => 'A', 'name_en' => 'A', 'slug' => 'orgA-conc', 'is_active' => true]);
        $applicant = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'a-conc@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        User::create([
            'organization_id' => $org->id, 'name' => 's', 'email' => 's-conc@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'staff', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        ServiceDefinition::create([
            'organization_id' => $org->id, 'code' => 'CONC',
            'name_ar' => 'ت', 'name_en' => 'C',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'review', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
                ]],
                'certificate' => ['validity_months' => 12, 'title_ar' => '.', 'title_en' => '.', 'fields_on_cert' => []],
                'fields' => [
                    ['id' => 'basin_number',           'label_ar' => 'ح', 'label_en' => 'b', 'type' => 'text'],
                    ['id' => 'parcel_number',          'label_ar' => 'ق', 'label_en' => 'p', 'type' => 'text'],
                    ['id' => 'basin_or_location_name', 'label_ar' => 'س', 'label_en' => 'n', 'type' => 'text'],
                    ['id' => 'contract_owner_name',    'label_ar' => 'م', 'label_en' => 'o', 'type' => 'text'],
                ],
                'documents' => [], 'sections' => [],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeDraft(int $orgId, int $svcId, int $applicantId, array $data): Application
    {
        return Application::create([
            'reference_number'      => 'DRAFT-' . uniqid(),
            'organization_id'       => $orgId,
            'service_definition_id' => $svcId,
            'applicant_id'          => $applicantId,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0, 'payment_status' => 'waived',
        ]);
    }
}
