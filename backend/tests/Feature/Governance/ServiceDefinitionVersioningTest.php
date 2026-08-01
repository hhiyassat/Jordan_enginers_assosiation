<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Governance\ApplicationVersionBinder;
use Modules\JeaServices\Governance\ServiceVersionPublisher;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Models\ServiceDefinitionVersion;
use RuntimeException;
use Tests\TestCase;

/**
 * SG-03 · version creation, immutability, supersede, binding, legacy classification.
 */
class ServiceDefinitionVersioningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $publisher;
    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->publisher = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'pub', 'email' => 'pub@t.test',
            'password'            => 'x', 'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->applicant = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'app', 'email' => 'app@t.test',
            'password'            => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    public function test_publishing_creates_immutable_version(): void
    {
        $service   = $this->makeService();
        $publisher = new ServiceVersionPublisher();

        $version = $publisher->publishNewVersion(
            service: $service,
            versionIdentifier: 'v1.0.0',
            publisher: $this->publisher,
            approvalReference: 'JEA-DEC-2026-42',
            approver: null, // publisher self-approves for test brevity
            approvalNotes: 'initial pilot',
        );

        $this->assertSame(ServiceDefinitionVersion::STATUS_PUBLISHED, $version->status);
        $this->assertNotNull($version->published_at);
        $this->assertSame($service->id, $version->service_definition_id);
        $this->assertSame($service->schema, $version->schema_snapshot);
        $this->assertNotEmpty($version->schema_hash);
    }

    public function test_published_version_rejects_schema_snapshot_edit(): void
    {
        $service = $this->makeService();
        $version = (new ServiceVersionPublisher())->publishNewVersion(
            service: $service,
            versionIdentifier: 'v1.0.0',
            publisher: $this->publisher,
            approvalReference: 'JEA-DEC-1',
        );

        $version->schema_snapshot = ['tampered' => true];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/frozen columns/');
        $version->save();
    }

    public function test_new_publish_supersedes_prior_version(): void
    {
        $service   = $this->makeService();
        $publisher = new ServiceVersionPublisher();
        $v1 = $publisher->publishNewVersion(service: $service, versionIdentifier: 'v1', publisher: $this->publisher, approvalReference: 'ref-1');
        $v2 = $publisher->publishNewVersion(service: $service, versionIdentifier: 'v2', publisher: $this->publisher, approvalReference: 'ref-2');

        $v1->refresh();
        $this->assertSame(ServiceDefinitionVersion::STATUS_SUPERSEDED, $v1->status);
        $this->assertNotNull($v1->effective_to);
        $this->assertSame($v1->id, $v2->supersedes_version_id);
    }

    public function test_schema_hash_is_stable_for_same_payload(): void
    {
        $schema = ['b' => 2, 'a' => 1, 'nested' => ['z' => 3, 'y' => 4]];
        $h1 = ServiceDefinitionVersion::computeSchemaHash($schema);
        $h2 = ServiceDefinitionVersion::computeSchemaHash($schema);
        $this->assertSame($h1, $h2);
    }

    public function test_schema_hash_stable_across_key_order(): void
    {
        $a = ['x' => 1, 'y' => ['nested' => true]];
        $b = ['y' => ['nested' => true], 'x' => 1];
        $this->assertSame(
            ServiceDefinitionVersion::computeSchemaHash($a),
            ServiceDefinitionVersion::computeSchemaHash($b),
        );
    }

    public function test_bind_or_classify_legacy_when_no_published_version(): void
    {
        $service = $this->makeService();
        $app     = $this->makeApp($service);
        $binder  = new ApplicationVersionBinder(new ServiceVersionPublisher());

        $classification = $binder->bindOrClassifyLegacy($app);

        $this->assertSame('LEGACY_UNVERSIONED', $classification);
        $this->assertNull($app->service_definition_version_id);
    }

    public function test_bind_populates_fk_when_published_version_exists(): void
    {
        $service = $this->makeService();
        $version = (new ServiceVersionPublisher())->publishNewVersion(
            service: $service, versionIdentifier: 'v1', publisher: $this->publisher, approvalReference: 'r',
        );
        $app    = $this->makeApp($service);
        $binder = new ApplicationVersionBinder(new ServiceVersionPublisher());

        $classification = $binder->bindOrClassifyLegacy($app);

        $this->assertSame('BOUND', $classification);
        $this->assertSame($version->id, $app->service_definition_version_id);
    }

    public function test_second_bind_does_not_switch_version(): void
    {
        $service = $this->makeService();
        $publisher = new ServiceVersionPublisher();
        $v1 = $publisher->publishNewVersion(service: $service, versionIdentifier: 'v1', publisher: $this->publisher, approvalReference: 'r1');
        $app = $this->makeApp($service);
        $binder = new ApplicationVersionBinder(new ServiceVersionPublisher());
        $binder->bindOrClassifyLegacy($app);
        $app->save();

        // publish a new version — v2 becomes the currently-published
        $v2 = $publisher->publishNewVersion(service: $service, versionIdentifier: 'v2', publisher: $this->publisher, approvalReference: 'r2');

        $reBind = $binder->bindOrClassifyLegacy($app->fresh());
        $this->assertSame('ALREADY_BOUND', $reBind);
        $this->assertSame($v1->id, $app->fresh()->service_definition_version_id);
    }

    public function test_application_classification_matches_binding_state(): void
    {
        $service = $this->makeService();
        $app     = $this->makeApp($service);

        $this->assertSame('LEGACY_UNVERSIONED', $app->legacyVersioningClassification());

        (new ServiceVersionPublisher())->publishNewVersion(
            service: $service, versionIdentifier: 'v1', publisher: $this->publisher, approvalReference: 'r',
        );
        (new ApplicationVersionBinder(new ServiceVersionPublisher()))->bindOrClassifyLegacy($app);

        $this->assertSame('BOUND', $app->legacyVersioningClassification());
    }

    private function makeService(): ServiceDefinition
    {
        return ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'            => 'VER-001',
            'name_ar'         => 'خدمة',
            'name_en'         => 'Service',
            'currency'        => 'JOD',
            'schema'          => [
                'workflow'  => ['stages' => [['id' => 'review', 'role' => 'staff']]],
                'fields'    => [['id' => 'x', 'type' => 'text']],
                'documents' => [],
                'fee'       => ['type' => 'fixed', 'amount' => 25],
            ],
            'status'          => 'active',
        ]);
    }

    private function makeApp(ServiceDefinition $service): Application
    {
        return Application::create([
            'reference_number'      => 'ref-' . uniqid(),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'pending',
        ]);
    }
}
