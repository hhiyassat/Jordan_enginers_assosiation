<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * The eight named lifecycle states required by
 * docs/architecture/service-governance/SG-01-service-lifecycle-constitution.md.
 *
 * These are the DERIVED states — they are computed from the columns on
 * service_definitions via ServiceDefinition::lifecycle(). No column stores
 * this value directly.
 */
final class ServiceLifecycleState
{
    public const DRAFT                 = 'DRAFT';
    public const CONFIGURED            = 'CONFIGURED';
    public const TECHNICALLY_VALIDATED = 'TECHNICALLY_VALIDATED';
    public const AWAITING_UAT          = 'AWAITING_UAT';
    public const UAT_APPROVED          = 'UAT_APPROVED';
    public const PUBLISHED             = 'PUBLISHED';
    public const SUSPENDED             = 'SUSPENDED';
    public const RETIRED               = 'RETIRED';

    public const ALL = [
        self::DRAFT,
        self::CONFIGURED,
        self::TECHNICALLY_VALIDATED,
        self::AWAITING_UAT,
        self::UAT_APPROVED,
        self::PUBLISHED,
        self::SUSPENDED,
        self::RETIRED,
    ];
}
