<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable SRV-001 workflow version identifier.
 *
 * A workflow version binds a graph of {states × actions × transitions}
 * to authorisation dimensions:
 *   • sourceStatus         — where the graph came from
 *   • businessApprovalStatus
 *   • implementationAuthorization
 *   • publicationAuthorization
 *   • runtimeStatus         — INACTIVE | PILOT | ACTIVE | RETIRED
 *
 * Existing applications remain bound to their original workflow
 * version — the version identifier appears in every transition
 * decision so historical decisions never depend on graph mutations.
 */
final class WorkflowVersion
{
    public const RUNTIME_INACTIVE = 'INACTIVE';
    public const RUNTIME_PILOT    = 'PILOT';
    public const RUNTIME_ACTIVE   = 'ACTIVE';
    public const RUNTIME_RETIRED  = 'RETIRED';

    /** @param list<string> $blockingOds */
    public function __construct(
        public readonly string $versionId,
        public readonly string $serviceCode,
        public readonly string $sourceStatus,
        public readonly string $businessApprovalStatus,
        public readonly string $implementationAuthorization,
        public readonly string $publicationAuthorization,
        public readonly string $runtimeStatus,
        public readonly ?string $effectiveFrom,
        public readonly ?string $effectiveTo,
        public readonly array $blockingOds = [],
    ) {
        if ($versionId === '' || $serviceCode === '') {
            throw new InvalidArgumentException('WorkflowVersion: versionId + serviceCode required');
        }
        if (! in_array(
            $runtimeStatus,
            [self::RUNTIME_INACTIVE, self::RUNTIME_PILOT, self::RUNTIME_ACTIVE, self::RUNTIME_RETIRED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown runtimeStatus: {$runtimeStatus}");
        }
    }

    public function isRuntimeActive(): bool
    {
        return $this->runtimeStatus === self::RUNTIME_ACTIVE;
    }
}
