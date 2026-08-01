<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\Contracts;

/**
 * TD-06 · Object-storage port.
 *
 * Domain never touches file bytes. Callers pass an opaque handle
 * across this boundary; the adapter is responsible for reading /
 * writing bytes to whatever storage the deployment uses.
 *
 * INVARIANT: `PRODUCTION_STORAGE_ADAPTER_STATUS` is not claimed
 * true by this port merely because a class implements the interface
 * — see the `CONTROL_MODELLED / ADAPTER_IMPLEMENTED / ADAPTER_TESTED
 * / PRODUCTION_CONFIGURED / PRODUCTION_VERIFIED` axis in the TD-06
 * report.
 */
interface ObjectStoragePort
{
    /**
     * Register a new opaque handle. Adapter allocates the storage
     * key + persists the bytes referenced by the handle (adapter
     * boundary — Domain does not read the handle itself).
     */
    public function register(string $inputHandle, string $applicationScope): string;

    /**
     * Return a signed URL / redirect target valid for a short window.
     * Adapter is responsible for enforcing the "short window" and
     * for signing. Domain does not sign anything.
     */
    public function signedAccessUrl(string $storageKey, int $ttlSeconds): string;

    /**
     * Adapter-side self-test.  Returns a string constant declaring
     * the adapter's operational state — modelled ONLY / configured
     * ONLY / verified.
     */
    public function operationalState(): string;
}
