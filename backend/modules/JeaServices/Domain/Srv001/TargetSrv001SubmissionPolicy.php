<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001;

use Modules\JeaServices\Domain\Srv001\Calculators\TargetExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetNetDepthTableCalculator;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetWellsCountCalculator;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001ExplorationStatus;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001CalculationEvidence;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001DerivedValues;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001SubmissionInputs;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001ValidationErrors;
use Modules\JeaServices\Governance\ServiceSubmissionDecision;
use Modules\JeaServices\Governance\ServiceSubmissionPolicy;
use Modules\JeaServices\Models\Application;

/**
 * TD-01 · Target-domain submission policy for SRV-001.
 *
 * Parallel implementation to LegacySrv001SubmissionPolicy (SG-06).
 * Numeric outputs identical to legacy — three Target* calculators
 * delegate to Legacy* to preserve behaviour. New surface:
 *
 *   • Typed value objects (Srv001SubmissionInputs, DerivedValues,
 *     CalculationEvidence, ValidationErrors)
 *   • TARGET_DOMAIN_PROVISIONAL classification on every snapshot
 *     payload (via calculator open_decisions)
 *   • Contract compliance with SG-05 ServiceSubmissionPolicy:
 *       - accepts typed input
 *       - returns typed decision
 *       - does NOT call \$app->save
 *       - does NOT mutate the passed Application entity
 *
 * STATUS: LEGACY_PILOT_PENDING_BUSINESS_APPROVAL — same as
 * LegacySrv001SubmissionPolicy per Ground Truth §3 and SRS v1.2
 * §الخلاصة (SRS itself declares non-approved).
 *
 * NOT WIRED to runtime. Consumers: unit tests. Runtime wiring is
 * RES-SG06-01 (post-TD program follow-up).
 *
 * See docs/architecture/srv001-target-domain/judgment-records/
 *     JDG-TD00-02-readiness-verdict.md item 1 for authorization.
 */
final class TargetSrv001SubmissionPolicy implements ServiceSubmissionPolicy
{
    public const SERVICE_CODE = 'SRV-001';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly TargetExplorationRequirementMatrixCalculator $matrix,
        private readonly TargetWellsCountCalculator $wells,
        private readonly TargetNetDepthTableCalculator $netDepth,
    ) {
    }

    public function serviceCode(): string
    {
        return self::SERVICE_CODE;
    }

    public function evaluate(Application $application): ServiceSubmissionDecision
    {
        $inputs = Srv001SubmissionInputs::fromApplicationData(
            is_array($application->data) ? $application->data : [],
        );

        // Rule 1 — government routing (mirrors legacy + Srv001Guard).
        $routingError = $this->checkGovernmentRouting($application, $inputs);
        if ($routingError !== null) {
            return ServiceSubmissionDecision::rejected($routingError->toArray());
        }

        // Rule 2 + 3 — exploration matrix + minimum-point enforcement.
        if ($inputs->floorCount === null || $inputs->floorArea === null || $inputs->actualExplorationPointCount === null) {
            return ServiceSubmissionDecision::rejected([
                'actual_exploration_point_count' => [
                    'يتعذَّر التحقق من عدد نقاط الاستكشاف: بيانات الطوابق/المساحة غير مكتملة.',
                ],
            ]);
        }

        $matrixResult = $this->matrix->compute([
            'floor_count' => $inputs->floorCount,
            'floor_area'  => $inputs->floorArea,
        ]);
        $wellsResult    = $this->wells->compute(['floor_area' => $inputs->floorArea]);
        $netDepthResult = $this->netDepth->compute(['floor_count' => $inputs->floorCount]);

        $evidence = new Srv001CalculationEvidence(
            explorationMatrix: $matrixResult,
            wellsCount:        $wellsResult,
            netDepth:          $netDepthResult,
        );

        $matrixOut = $matrixResult->outputs;

        // SPECIAL_STUDY_REQUIRED — accepted with technical-review flag.
        if (($matrixOut['status'] ?? null) === Srv001ExplorationStatus::SPECIAL_STUDY_REQUIRED) {
            $derived = new Srv001DerivedValues(
                explorationRequirementStatus:   $matrixOut['status'],
                explorationSpecialStudyReason:  $matrixOut['reason'] ?? null,
                minimumExplorationPointCount:   null,
                minimumTotalDepthLm:            null,
                technicalReviewRequired:        true,
                meetingWellsCount:              $this->wellsCount($wellsResult->outputs),
                meetingWellsBand:               $this->wellsBand($wellsResult->outputs),
                meetingNetDepthThirdM:          $this->netDepthValue($netDepthResult->outputs, 'third_m'),
                meetingNetDepthTwoThirdsM:      $this->netDepthValue($netDepthResult->outputs, 'two_thirds_m'),
                meetingNetDepthTotalM:          $this->netDepthValue($netDepthResult->outputs, 'total_m'),
            );

            return ServiceSubmissionDecision::accepted(
                derivedValues:        $derived->toApplicationDataArray(),
                warnings:             ['SRV-001 SPECIAL_STUDY_REQUIRED — downstream flow must route to JEA technical review.'],
                calculationSnapshots: $evidence->toSnapshotPayloads(),
            );
        }

        // CALCULATED path — enforce minimum + persist derived values.
        $minPts   = (int) $matrixOut['minimum_exploration_point_count'];
        $minDepth = (int) $matrixOut['minimum_total_depth_lm'];

        if ($inputs->actualExplorationPointCount < $minPts) {
            return ServiceSubmissionDecision::rejected([
                'actual_exploration_point_count' => [
                    sprintf(
                        'عدد نقاط الاستكشاف المنفذة (%d) أقل من الحد الأدنى المطلوب (%d) وفق جدول ص.230-231.',
                        $inputs->actualExplorationPointCount,
                        $minPts,
                    ),
                ],
            ]);
        }

        $derived = new Srv001DerivedValues(
            explorationRequirementStatus:  $matrixOut['status'],
            explorationSpecialStudyReason: null,
            minimumExplorationPointCount:  $minPts,
            minimumTotalDepthLm:           $minDepth,
            technicalReviewRequired:       false,
            meetingWellsCount:             $this->wellsCount($wellsResult->outputs),
            meetingWellsBand:              $this->wellsBand($wellsResult->outputs),
            meetingNetDepthThirdM:         $this->netDepthValue($netDepthResult->outputs, 'third_m'),
            meetingNetDepthTwoThirdsM:     $this->netDepthValue($netDepthResult->outputs, 'two_thirds_m'),
            meetingNetDepthTotalM:         $this->netDepthValue($netDepthResult->outputs, 'total_m'),
        );

        return ServiceSubmissionDecision::accepted(
            derivedValues:        $derived->toApplicationDataArray(),
            calculationSnapshots: $evidence->toSnapshotPayloads(),
        );
    }

    private function checkGovernmentRouting(Application $application, Srv001SubmissionInputs $inputs): ?Srv001ValidationErrors
    {
        $service = $application->serviceDefinition;
        if ($service === null || $service->code !== self::SERVICE_CODE) {
            return null; // Safety net — dispatch is service-scoped upstream.
        }
        $routing = $service->schema['workflow']['routing'] ?? [];
        if (!is_array($routing)) {
            return null;
        }
        $data = is_array($application->data) ? $application->data : [];

        foreach ($routing as $rule) {
            if (!is_array($rule) || ($rule['action'] ?? null) !== 'ROUTE_TO_SERVICE') {
                continue;
            }
            $when = $rule['when'] ?? [];
            if (!is_array($when) || $when === []) {
                continue;
            }
            $match = true;
            foreach ($when as $k => $v) {
                if (($data[$k] ?? null) !== $v) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $message = (string) ($rule['message_ar']
                    ?? "المشاريع الحكومية تُقدَّم عبر خدمة {$rule['target']}.");
                return Srv001ValidationErrors::empty()->withError('project_sector', $message);
            }
        }
        return null;
    }

    /** @param array<string, mixed> $wells */
    private function wellsCount(array $wells): ?int
    {
        return ($wells['status'] ?? null) === 'CALCULATED'
            ? (int) ($wells['wells'] ?? 0) ?: null
            : null;
    }

    /** @param array<string, mixed> $wells */
    private function wellsBand(array $wells): ?string
    {
        return ($wells['status'] ?? null) === 'CALCULATED'
            ? (string) ($wells['band'] ?? '') ?: null
            : null;
    }

    /**
     * @param array<string, mixed> $netDepth
     */
    private function netDepthValue(array $netDepth, string $key): ?int
    {
        if (($netDepth['status'] ?? null) !== 'CALCULATED') {
            return null;
        }
        return isset($netDepth[$key]) ? (int) $netDepth[$key] : null;
    }
}
