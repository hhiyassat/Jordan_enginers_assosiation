<?php

declare(strict_types=1);

namespace Modules\JeaServices\Services;

use App\Contracts\Services\ServiceLockLookup;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * EloquentServiceLockLookup — JeaServices implementation of the
 * Platform `ServiceLockLookup` contract.
 *
 * Bound in `JeaServicesServiceProvider::register` so Platform-side
 * consumers (currently `AiSchemaController` + `NashmiIntegrationService`)
 * can check lock state without importing this module.
 */
final class EloquentServiceLockLookup implements ServiceLockLookup
{
    public function isLocked(int $organizationId, int $serviceId): ?bool
    {
        $svc = ServiceDefinition::where('organization_id', $organizationId)
            ->find($serviceId);
        return $svc === null ? null : $svc->isLocked();
    }

    public function codeFor(int $organizationId, int $serviceId): ?string
    {
        $svc = ServiceDefinition::where('organization_id', $organizationId)
            ->find($serviceId);
        return $svc?->code;
    }
}
