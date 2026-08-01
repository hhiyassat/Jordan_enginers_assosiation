<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

/**
 * TD-01 · Typed carrier for the derived values a SRV-001 submission
 * produces. Callers persist these onto `applications.data` — the
 * policy itself never writes.
 *
 * SCOPE: same set of derived keys as the pilot's Srv001Guard writes
 * today, plus a `target_domain_provisional` marker so any consumer
 * can distinguish target-produced from legacy-produced writes.
 *
 * Numeric values preserve legacy semantics — TargetSrv001SubmissionPolicy
 * delegates to Legacy* calculators for now (per JDG-TD00-02).
 */
final class Srv001DerivedValues
{
    public function __construct(
        public readonly string $explorationRequirementStatus,   // CALCULATED | SPECIAL_STUDY_REQUIRED | INELIGIBLE
        public readonly ?string $explorationSpecialStudyReason,
        public readonly ?int $minimumExplorationPointCount,
        public readonly ?int $minimumTotalDepthLm,
        public readonly bool $technicalReviewRequired,
        public readonly ?int $meetingWellsCount,
        public readonly ?string $meetingWellsBand,
        public readonly ?int $meetingNetDepthThirdM,
        public readonly ?int $meetingNetDepthTwoThirdsM,
        public readonly ?int $meetingNetDepthTotalM,
        public readonly bool $targetDomainProvisional = true,
    ) {
    }

    /**
     * Serialise for merge into `applications.data`. Matches the key
     * set the pilot's Srv001Guard writes today so downstream consumers
     * (frontend read paths, report generators) see the same shape
     * regardless of source.
     *
     * @return array<string, mixed>
     */
    public function toApplicationDataArray(): array
    {
        return array_filter(
            [
                'exploration_requirement_status'    => $this->explorationRequirementStatus,
                'exploration_special_study_reason'  => $this->explorationSpecialStudyReason,
                'minimum_exploration_point_count'   => $this->minimumExplorationPointCount,
                'minimum_total_depth_lm'            => $this->minimumTotalDepthLm,
                'technical_review_required'         => $this->technicalReviewRequired,
                'meeting_wells_count'               => $this->meetingWellsCount,
                'meeting_wells_band'                => $this->meetingWellsBand,
                'meeting_net_depth_third_m'         => $this->meetingNetDepthThirdM,
                'meeting_net_depth_two_thirds_m'    => $this->meetingNetDepthTwoThirdsM,
                'meeting_net_depth_total_m'         => $this->meetingNetDepthTotalM,
                'target_domain_provisional'         => $this->targetDomainProvisional,
            ],
            static fn ($v) => $v !== null,
        );
    }
}
