<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * ServiceLockLookup — provider-neutral read of the JEA service catalog's
 * lock state for consumers that MUST refuse to mutate a locked service
 * but should not import the JEA `ServiceDefinition` model directly.
 *
 * H-07: created so `Plugins\AiSchema\Http\Controllers\AiSchemaController`
 * and `Integrations\Nashmi\Services\NashmiIntegrationService` can gate
 * their behaviour on service-lock state without a hard PHP-level
 * dependency on `Modules\JeaServices`. JeaServicesServiceProvider
 * binds an Eloquent implementation.
 */
interface ServiceLockLookup
{
    /**
     * Return true if the (organization, service) pair exists AND is
     * locked. Return false if it exists and is unlocked. Return null
     * if the service does not exist.
     */
    public function isLocked(int $organizationId, int $serviceId): ?bool;

    /**
     * Return the service's public code (used in error responses to
     * echo which service was refused). Null when not found.
     */
    public function codeFor(int $organizationId, int $serviceId): ?string;
}
