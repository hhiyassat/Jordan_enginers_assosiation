<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Models\ServiceDefinitionVersion;

/**
 * SG-03 · Creates and publishes immutable service-definition versions
 * from the current `service_definitions.schema` row.
 *
 * Publishing a version is a two-step commit inside a single transaction:
 *   1) Insert the version with status='DRAFT' and a snapshot of the
 *      current schema.
 *   2) Transition to status='PUBLISHED', mark the prior published version
 *      (if any) as SUPERSEDED, and stamp `published_at` / `published_by`.
 *
 * Callers are responsible for verifying that ServicePublicationPolicy has
 * cleared the service for publication (SG-01). This publisher does not
 * re-run the policy — it is a pure persistence operation.
 */
final class ServiceVersionPublisher
{
    public function publishNewVersion(
        ServiceDefinition $service,
        string $versionIdentifier,
        User $publisher,
        string $approvalReference,
        ?User $approver = null,
        ?string $approvalNotes = null,
    ): ServiceDefinitionVersion {
        return DB::transaction(function () use ($service, $versionIdentifier, $publisher, $approvalReference, $approver, $approvalNotes) {
            // RC-03 · Serialise concurrent publishes for the same service.
            // Without this lock, two concurrent publishes with distinct
            // identifiers can both read "no prior published" and both
            // promote to PUBLISHED, leaving the service with two current
            // versions. The `lockForUpdate()` on the parent
            // ServiceDefinition row forces one publisher to wait until the
            // other's transaction commits.
            ServiceDefinition::query()->whereKey($service->id)->lockForUpdate()->first();

            $schema = $service->schema ?? [];

            $version = new ServiceDefinitionVersion();
            $version->service_definition_id = $service->id;
            $version->version_identifier    = $versionIdentifier;
            $version->schema_snapshot       = $schema;
            $version->schema_hash           = ServiceDefinitionVersion::computeSchemaHash($schema);
            $version->status                = ServiceDefinitionVersion::STATUS_DRAFT;
            $version->created_by            = $publisher->id;
            $version->approved_by           = $approver !== null ? $approver->id : $publisher->id;
            $version->approval_reference    = $approvalReference;
            $version->approval_notes        = $approvalNotes;
            $version->save();

            // Supersede the previous published version, if any.
            $priorPublished = ServiceDefinitionVersion::query()
                ->where('service_definition_id', $service->id)
                ->where('status', ServiceDefinitionVersion::STATUS_PUBLISHED)
                ->orderByDesc('published_at')
                ->first();

            if ($priorPublished !== null) {
                $priorPublished->status       = ServiceDefinitionVersion::STATUS_SUPERSEDED;
                $priorPublished->effective_to = now();
                $priorPublished->save();
                $version->supersedes_version_id = $priorPublished->id;
            }

            // Transition to PUBLISHED (must be a separate save so the
            // immutability observer sees the previous status as DRAFT).
            $version->status         = ServiceDefinitionVersion::STATUS_PUBLISHED;
            $version->published_at   = now();
            $version->published_by   = $publisher->id;
            $version->effective_from = $version->effective_from ?? now();
            $version->save();

            return $version->fresh() ?? $version;
        });
    }

    public function currentPublishedFor(ServiceDefinition $service): ?ServiceDefinitionVersion
    {
        return ServiceDefinitionVersion::query()
            ->where('service_definition_id', $service->id)
            ->where('status', ServiceDefinitionVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();
    }
}
