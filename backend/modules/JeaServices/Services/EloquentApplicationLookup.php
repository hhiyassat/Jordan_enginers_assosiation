<?php

declare(strict_types=1);

namespace Modules\JeaServices\Services;

use App\Contracts\Applications\ApplicationLookup;
use App\Contracts\Applications\ApplicationSnapshot;
use Modules\JeaServices\Models\Application;

/**
 * EloquentApplicationLookup — JeaServices implementation of the
 * Platform `ApplicationLookup` contract.
 *
 * Bound in `JeaServicesServiceProvider::register` so sibling modules
 * (JeaProjects, JeaDiscipline, JeaDues) can read applications
 * without importing this module.
 */
final class EloquentApplicationLookup implements ApplicationLookup
{
    public function find(int $id): ?ApplicationSnapshot
    {
        $app = Application::withoutOrgScope()->find($id);
        return $app === null ? null : $this->toSnapshot($app);
    }

    /**
     * @param  list<string>  $statuses
     * @return \Generator<ApplicationSnapshot>
     */
    public function forOrganizationInStatuses(int $organizationId, array $statuses): \Generator
    {
        foreach (
            Application::forOrganization($organizationId)
                ->whereIn('status', $statuses)
                ->cursor()
                as $app
        ) {
            yield $this->toSnapshot($app);
        }
    }

    /**
     * CS-04: public helper for callers that already have an Application
     * model in hand (e.g. JeaServices\Http\Controllers\ApplicationController)
     * and want to hand a snapshot to a sibling-module consumer
     * (SanctionGuard) without a redundant DB round-trip.
     *
     * Kept static + public so the caller can invoke without resolving
     * the lookup from the container.
     */
    public static function snapshotOf(Application $app): ApplicationSnapshot
    {
        return (new self())->toSnapshot($app);
    }

    private function toSnapshot(Application $app): ApplicationSnapshot
    {
        return new ApplicationSnapshot(
            id:                    (int) $app->id,
            reference_number:      (string) $app->reference_number,
            organization_id:       (int) $app->organization_id,
            service_definition_id: (int) $app->service_definition_id,
            applicant_id:          (int) $app->applicant_id,
            status:                (string) $app->status,
            current_stage:         $app->current_stage,
            fee_amount:            $app->fee_amount,
            basin_number:          $app->basin_number,
            parcel_number:         $app->parcel_number,
            data:                  is_array($app->data) ? $app->data : [],
        );
    }
}
