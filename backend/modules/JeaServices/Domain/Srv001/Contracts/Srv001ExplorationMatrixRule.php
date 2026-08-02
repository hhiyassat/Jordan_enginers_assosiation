<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01A · Domain-owned port for the SRV-001 exploration-requirement
 * matrix rule (SRS v1.2 §4.1 / كتاب التعليمات الفنية 2025 ص 230-231).
 *
 * Introduced to remove the Domain → Governance\Srv001\Legacy* direct
 * dependency (see JDG-TD01A-02). The Target* calculator depends on
 * this port; an adapter outside the Domain layer supplies the
 * implementation. The pilot-era adapter delegates to Legacy* and
 * carries the TARGET_DOMAIN_PROVISIONAL classification.
 */
interface Srv001ExplorationMatrixRule
{
    /**
     * Compute the matrix decision for a building's (floor_count, floor_area).
     *
     * @return array<string, mixed>  raw domain output — same shape the
     *   legacy `ExplorationRequirementMatrix::compute` returns today
     *   (status: CALCULATED|SPECIAL_STUDY_REQUIRED|INELIGIBLE, plus
     *   minimum_exploration_point_count / minimum_total_depth_lm on
     *   CALCULATED paths, and reason on SPECIAL_STUDY paths).
     */
    public function compute(int $floorCount, float $floorArea): array;

    /**
     * Numeric id of the RuleVersion row that carries this rule's
     * business_approval_status. Written by the Target* calculator into
     * `ServiceCalculationResult::ruleVersionId` for CalculationSnapshot
     * traceability.
     */
    public function ruleVersionId(): int;

    /**
     * OD blockers + governance disclaimers to surface into
     * `ServiceCalculationResult::openDecisions`. Populated by the
     * adapter so the Target* wrapper stays governance-free.
     *
     * @return list<string>
     */
    public function openDecisions(): array;
}
