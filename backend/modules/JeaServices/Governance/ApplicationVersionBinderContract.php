<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Models\Application;

/**
 * TD-02 · Contract extracted from the concrete `ApplicationVersionBinder`
 * so use cases (SubmitApplicationUseCase) can depend on the abstraction
 * rather than the concrete final class. Enables clean test doubles for
 * rollback / failure-scenario coverage.
 *
 * The single production implementation remains `ApplicationVersionBinder`
 * (SG-03). Runtime binding container binds this interface to that class
 * — see JeaServicesServiceProvider (future TD phase; TD-02 wires via
 * direct instantiation in tests).
 */
interface ApplicationVersionBinderContract
{
    /**
     * Assign `service_definition_version_id` on the application if a
     * published version exists.
     *
     * @return string  classification: 'ALREADY_BOUND' | 'BOUND'
     *                 | 'LEGACY_UNVERSIONED' | 'NO_SERVICE_DEFINITION'
     */
    public function bindOrClassifyLegacy(Application $app): string;
}
