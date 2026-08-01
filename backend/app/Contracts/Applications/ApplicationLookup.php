<?php

declare(strict_types=1);

namespace App\Contracts\Applications;

/**
 * ApplicationLookup — provider-neutral read of the JEA
 * `Application` entity for sibling modules (JeaProjects,
 * JeaDiscipline, JeaDues) that must not import
 * `Modules\JeaServices\Models\Application` directly.
 *
 * H-07 remediation. JeaServicesServiceProvider binds the concrete
 * implementation (EloquentApplicationLookup) so the interface hides
 * every Eloquent detail behind an immutable ApplicationSnapshot DTO.
 *
 * Deliberately narrow. Grows as sibling modules migrate off the raw
 * model — each new use case adds a well-typed method here, keeping
 * the surface auditable.
 */
interface ApplicationLookup
{
    /**
     * Find an application by id. Returns null if the row does not
     * exist. No org scope — callers pass the org id explicitly.
     */
    public function find(int $id): ?ApplicationSnapshot;

    /**
     * Iterate all applications belonging to an organization whose
     * status is in the supplied whitelist. Returned as a generator
     * of snapshots — the caller controls memory pressure via how
     * many they consume.
     *
     * @param  list<string>  $statuses  Application status strings to include.
     * @return \Generator<ApplicationSnapshot>
     */
    public function forOrganizationInStatuses(int $organizationId, array $statuses): \Generator;
}
