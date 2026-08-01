<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\JeaServices\Governance\ApplicationVersionBinder;
use Modules\JeaServices\Governance\ServiceVersionPublisher;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Models\ServiceDefinitionVersion;
use RuntimeException;
use Tests\TestCase;

/**
 * RC-03 · concurrency evidence for the SG-03 service_definition_versions
 * mechanism. Uses pcntl_fork to prove that under real cross-process load:
 *
 *   - Concurrent publish attempts with the same version_identifier collide
 *     on the (service_definition_id, version_identifier) unique index —
 *     no two rows share the same identifier.
 *   - After N concurrent publishes with DISTINCT identifiers, exactly ONE
 *     row remains status='PUBLISHED' (the rest superseded) — no two
 *     current versions exist simultaneously.
 *   - Concurrent application-binder invocations against the same
 *     application never overwrite an already-bound version id (idempotent
 *     under race).
 *   - Immutability observer refuses updates to a PUBLISHED version's
 *     frozen columns even when triggered from concurrent connections.
 *
 * Runs only on Postgres — pcntl_fork over sqlite in-memory has no shared
 * state.
 */
class ServiceVersionConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension required');
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('real concurrency test requires PostgreSQL');
        }
        $this->truncate();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->truncate();
        }
        parent::tearDown();
    }

    private function truncate(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        $tables = [
            'calculation_snapshots', 'rule_versions', 'rule_definitions',
            'application_reviews', 'application_documents',
            'applications', 'service_definition_versions', 'service_definitions',
            'personal_access_tokens', 'users', 'organizations',
        ];
        foreach ($tables as $t) {
            DB::statement("TRUNCATE TABLE \"{$t}\" RESTART IDENTITY CASCADE");
        }
    }

    public function test_concurrent_publish_with_same_identifier_all_collide_on_unique_index(): void
    {
        [$service, $publisher] = $this->seedServiceAndPublisher();
        $serviceId = $service->id;
        $publisherId = $publisher->id;

        // Fork 6 children all attempting to publish "v1.0.0" for the same service.
        // The unique(service_definition_id, version_identifier) constraint must
        // reject all but one; children observe QueryException.
        $parallel = 6;
        $collected = $this->forkAndCollect($parallel, function () use ($serviceId, $publisherId) {
            try {
                $svc = ServiceDefinition::query()->findOrFail($serviceId);
                $usr = User::query()->findOrFail($publisherId);
                (new ServiceVersionPublisher())->publishNewVersion(
                    service: $svc,
                    versionIdentifier: 'v1.0.0',
                    publisher: $usr,
                    approvalReference: 'concurrent-test',
                );
                return ['status' => 'OK'];
            } catch (\Throwable $e) {
                return ['status' => 'ERR', 'msg' => $e->getMessage()];
            }
        });

        // Exactly ONE row must exist for that identifier.
        $rowCount = ServiceDefinitionVersion::query()
            ->where('service_definition_id', $serviceId)
            ->where('version_identifier', 'v1.0.0')
            ->count();
        $this->assertSame(1, $rowCount, 'unique constraint must permit exactly one row for a given (service, version_identifier)');

        // At least one child succeeded; others errored.
        $successCount = count(array_filter($collected, fn ($r) => ($r['status'] ?? '') === 'OK'));
        $this->assertGreaterThanOrEqual(1, $successCount, 'at least one publish must succeed');
    }

    public function test_only_one_published_version_after_concurrent_publishes_with_distinct_identifiers(): void
    {
        [$service, $publisher] = $this->seedServiceAndPublisher();
        $serviceId = $service->id;
        $publisherId = $publisher->id;

        // Fork N children each publishing a unique identifier. After the
        // dust settles, exactly ONE row must be status=PUBLISHED (the
        // last to commit), all others SUPERSEDED.
        $parallel = 5;
        $this->forkAndCollect($parallel, function () use ($serviceId, $publisherId) {
            try {
                $svc = ServiceDefinition::query()->findOrFail($serviceId);
                $usr = User::query()->findOrFail($publisherId);
                (new ServiceVersionPublisher())->publishNewVersion(
                    service: $svc,
                    versionIdentifier: 'v-' . uniqid('', true),
                    publisher: $usr,
                    approvalReference: 'concurrent-distinct',
                );
                return ['status' => 'OK'];
            } catch (\Throwable $e) {
                return ['status' => 'ERR', 'msg' => $e->getMessage()];
            }
        });

        $publishedCount = ServiceDefinitionVersion::query()
            ->where('service_definition_id', $serviceId)
            ->where('status', ServiceDefinitionVersion::STATUS_PUBLISHED)
            ->count();
        $this->assertSame(1, $publishedCount, 'exactly one row may be PUBLISHED for a service at any time');

        $supersededCount = ServiceDefinitionVersion::query()
            ->where('service_definition_id', $serviceId)
            ->where('status', ServiceDefinitionVersion::STATUS_SUPERSEDED)
            ->count();
        $this->assertGreaterThanOrEqual(1, $supersededCount, 'earlier publishes must be SUPERSEDED');
    }

    public function test_concurrent_binder_invocations_never_switch_bound_version(): void
    {
        [$service, $publisher] = $this->seedServiceAndPublisher();
        $applicant = User::create([
            'organization_id' => $service->organization_id,
            'name' => 'app', 'email' => 'app@r.test', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);

        // Publish v1 then create an application; then publish v2. Multiple
        // concurrent binder invocations must not switch the application
        // from v1 → v2 because the binder is idempotent-on-bound.
        $v1 = (new ServiceVersionPublisher())->publishNewVersion(
            service: $service, versionIdentifier: 'v1', publisher: $publisher, approvalReference: 'r1',
        );

        $app = Application::create([
            'reference_number' => 'ref-cc-1',
            'organization_id' => $service->organization_id,
            'service_definition_id' => $service->id,
            'applicant_id' => $applicant->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [], 'fee_amount' => 0, 'payment_status' => 'pending',
        ]);
        // Initial binding to v1
        $binder = new ApplicationVersionBinder(new ServiceVersionPublisher());
        $binder->bindOrClassifyLegacy($app);
        $app->save();
        $this->assertSame($v1->id, $app->fresh()->service_definition_version_id);

        // Publish v2
        $v2 = (new ServiceVersionPublisher())->publishNewVersion(
            service: $service, versionIdentifier: 'v2', publisher: $publisher, approvalReference: 'r2',
        );

        $appId = $app->id;
        $parallel = 4;
        $this->forkAndCollect($parallel, function () use ($appId) {
            $a = Application::query()->findOrFail($appId);
            (new ApplicationVersionBinder(new ServiceVersionPublisher()))
                ->bindOrClassifyLegacy($a);
            $a->save();
            return ['status' => 'OK'];
        });

        // Application must still reference v1 (not v2) — binder is idempotent-on-bound.
        $this->assertSame($v1->id, $app->fresh()->service_definition_version_id,
            'binder must not switch a bound application to a newer version under concurrent load');
        $this->assertNotSame($v2->id, $app->fresh()->service_definition_version_id);
    }

    public function test_immutability_observer_rejects_concurrent_schema_snapshot_mutation(): void
    {
        [$service, $publisher] = $this->seedServiceAndPublisher();
        $version = (new ServiceVersionPublisher())->publishNewVersion(
            service: $service, versionIdentifier: 'v-immut-1', publisher: $publisher, approvalReference: 'r-immut',
        );
        $versionId = $version->id;

        $parallel = 3;
        $collected = $this->forkAndCollect($parallel, function () use ($versionId) {
            try {
                $v = ServiceDefinitionVersion::query()->findOrFail($versionId);
                $v->schema_snapshot = ['tampered_by' => getmypid()];
                $v->save();
                return ['status' => 'UNEXPECTED_OK'];
            } catch (RuntimeException $e) {
                return ['status' => 'REJECTED', 'msg' => $e->getMessage()];
            } catch (\Throwable $e) {
                return ['status' => 'OTHER', 'msg' => $e->getMessage()];
            }
        });

        $rejected = count(array_filter($collected, fn ($r) => ($r['status'] ?? '') === 'REJECTED'));
        $this->assertSame($parallel, $rejected, 'every concurrent mutation attempt must be rejected by the observer');

        // Snapshot column must be unchanged.
        $fresh = ServiceDefinitionVersion::query()->findOrFail($versionId);
        $this->assertArrayNotHasKey('tampered_by', $fresh->schema_snapshot);
    }

    // ── infrastructure ────────────────────────────────────────────────

    /** @return array{0: ServiceDefinition, 1: User} */
    private function seedServiceAndPublisher(): array
    {
        $org = Organization::create([
            'name_ar' => 'cc-org', 'name_en' => 'cc', 'slug' => 'cc-' . uniqid(), 'is_active' => true,
        ]);
        $publisher = User::create([
            'organization_id' => $org->id,
            'name' => 'pub', 'email' => 'pub-' . uniqid() . '@r.test', 'password' => 'x',
            'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $service = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code' => 'CC-' . strtoupper(substr(uniqid(), -6)),
            'name_ar' => 'x', 'name_en' => 'x', 'currency' => 'JOD',
            'schema' => [
                'workflow' => ['stages' => [['id' => 'r', 'role' => 'staff']]],
                'fields' => [], 'documents' => [],
                'fee' => ['type' => 'fixed', 'amount' => 10],
            ],
            'status' => 'active',
        ]);
        return [$service, $publisher];
    }

    /**
     * @param  int  $parallel
     * @param  callable  $work
     * @return list<array<string, mixed>>
     */
    private function forkAndCollect(int $parallel, callable $work): array
    {
        DB::disconnect();

        $pids = [];
        $tmpDir = sys_get_temp_dir() . '/esp-cc-' . uniqid();
        mkdir($tmpDir, 0777, true);

        for ($i = 0; $i < $parallel; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork() failed');
            }
            if ($pid === 0) {
                // Child — reconnect to DB (fork inherits closed handle).
                DB::reconnect();
                $result = $work();
                file_put_contents("{$tmpDir}/{$i}.json", json_encode($result));
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $collected = [];
        for ($i = 0; $i < $parallel; $i++) {
            $file = "{$tmpDir}/{$i}.json";
            if (file_exists($file)) {
                $collected[] = json_decode(file_get_contents($file), true) ?? [];
                unlink($file);
            } else {
                $collected[] = ['status' => 'MISSING'];
            }
        }
        rmdir($tmpDir);
        return $collected;
    }
}
