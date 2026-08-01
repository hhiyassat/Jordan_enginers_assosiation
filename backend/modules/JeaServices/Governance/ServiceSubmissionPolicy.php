<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Models\Application;

/**
 * SG-05 · Contract for service-specific submission policies.
 *
 * A policy accepts the application entity and returns a typed
 * ServiceSubmissionDecision. It MUST NOT:
 *   - call $app->save
 *   - mutate the passed Application entity's persistent state
 *   - dispatch jobs, emit events, or call HTTP transports
 *   - open a DB transaction (the caller owns the transaction boundary)
 *
 * A policy MAY:
 *   - read Application, ServiceDefinition, ServiceDefinitionVersion
 *   - invoke pure calculators (ServiceCalculationPolicy implementations)
 *   - return derived values in the decision object
 *   - return snapshot payloads for CalculationSnapshotWriter
 *
 * SG-06 introduces LegacySrv001SubmissionPolicy — the first non-trivial
 * implementation — as a boundary around the current Srv001Guard.
 */
interface ServiceSubmissionPolicy
{
    public function serviceCode(): string;

    public function evaluate(Application $application): ServiceSubmissionDecision;
}
