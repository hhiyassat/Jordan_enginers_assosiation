<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01A · Domain-owned status vocabulary for the SRV-001 exploration
 * matrix outcome.
 *
 * Introduced so the Domain layer does not import
 * `Modules\JeaServices\Engine\ExplorationRequirementMatrix::STATUS_*`
 * constants (Engine is a lower layer). The three string values match
 * what the engine returns so no runtime translation is needed.
 */
final class Srv001ExplorationStatus
{
    public const CALCULATED             = 'CALCULATED';
    public const SPECIAL_STUDY_REQUIRED = 'SPECIAL_STUDY_REQUIRED';
    public const INELIGIBLE             = 'INELIGIBLE';
}
