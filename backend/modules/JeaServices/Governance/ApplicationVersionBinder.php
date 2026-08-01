<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Models\Application;

/**
 * SG-03 · Binds an application to the currently-published version of its
 * service definition at submit time. See JDG-SG03-02 for the timing
 * decision and JDG-SG03-03 for the LEGACY_UNVERSIONED classification.
 *
 * The binder is a small, side-effect-limited class. It:
 *   - looks up the currently-published version via ServiceVersionPublisher
 *   - assigns the FK if a published version exists
 *   - leaves the FK null when no published version exists (LEGACY_UNVERSIONED)
 *
 * It does NOT save the application — the caller (WorkflowEngine::submit)
 * saves inside its own transaction.
 */
final class ApplicationVersionBinder implements ApplicationVersionBinderContract
{
    public function __construct(
        private readonly ServiceVersionPublisher $publisher,
    ) {
    }

    /**
     * Assigns `service_definition_version_id` on the application if a
     * published version exists. Returns the classification for logging.
     *
     * Idempotent: once bound, a second call does not switch the binding.
     */
    public function bindOrClassifyLegacy(Application $app): string
    {
        if ($app->service_definition_version_id !== null) {
            return 'ALREADY_BOUND';
        }

        $service = $app->serviceDefinition;
        if ($service === null) {
            return 'NO_SERVICE_DEFINITION';
        }

        $version = $this->publisher->currentPublishedFor($service);
        if ($version === null) {
            return 'LEGACY_UNVERSIONED';
        }

        $app->service_definition_version_id = $version->id;
        return 'BOUND';
    }
}
