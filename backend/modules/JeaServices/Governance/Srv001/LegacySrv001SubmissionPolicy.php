<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance\Srv001;

use Modules\JeaServices\Engine\ExplorationRequirementMatrix;
use Modules\JeaServices\Governance\ServiceSubmissionDecision;
use Modules\JeaServices\Governance\ServiceSubmissionPolicy;
use Modules\JeaServices\Models\Application;

/**
 * SG-06 · legacy pilot boundary for SRV-001.
 *
 * Implements ServiceSubmissionPolicy (SG-05) by orchestrating three
 * ServiceCalculationPolicy adapters over the existing static calculators.
 * Returns a typed ServiceSubmissionDecision — does NOT call \$app->save,
 * does NOT mutate app.data.
 *
 * Status: LEGACY_PILOT_PENDING_BUSINESS_APPROVAL. Not Approved. Not
 * Canonical. Not Final. Not Target. Preserves current Srv001Guard
 * externally observable behaviour by using the same calculator outputs
 * and the same government-routing check.
 *
 * This class is not currently wired into the runtime — Srv001Guard remains
 * the runtime path. Wiring this class end-to-end is RES-SG06-01
 * (post-program follow-up).
 */
final class LegacySrv001SubmissionPolicy implements ServiceSubmissionPolicy
{
    public const SERVICE_CODE = 'SRV-001';

    public const STATUS_CLASSIFICATION = 'LEGACY_PILOT_PENDING_BUSINESS_APPROVAL';

    public function __construct(
        private readonly LegacyExplorationRequirementMatrixCalculator $matrix,
        private readonly LegacyWellsCountCalculator $wells,
        private readonly LegacyNetDepthTableCalculator $netDepth,
    ) {
    }

    public function serviceCode(): string
    {
        return self::SERVICE_CODE;
    }

    public function evaluate(Application $application): ServiceSubmissionDecision
    {
        $data = is_array($application->data) ? $application->data : [];

        // Rule 1 — government routing (mirrors Srv001Guard::checkRouting).
        $service = $application->serviceDefinition;
        if ($service !== null && $service->code === self::SERVICE_CODE) {
            $routing = $service->schema['workflow']['routing'] ?? [];
            $routingError = $this->checkRouting(is_array($routing) ? $routing : [], $data);
            if ($routingError !== null) {
                return ServiceSubmissionDecision::rejected([
                    'project_sector' => [$routingError],
                ]);
            }
        }

        // Rule 2 + 3 — exploration matrix.
        $floorCount = $this->intOrNull($data['floor_count'] ?? null);
        $floorArea  = $this->floatOrNull($data['floor_area'] ?? null);
        $actualPts  = $this->intOrNull($data['actual_exploration_point_count'] ?? null);

        if ($floorCount === null || $floorArea === null || $actualPts === null) {
            return ServiceSubmissionDecision::rejected([
                'actual_exploration_point_count' => [
                    'يتعذَّر التحقق من عدد نقاط الاستكشاف: بيانات الطوابق/المساحة غير مكتملة.',
                ],
            ]);
        }

        $matrixResult = $this->matrix->compute([
            'floor_count' => $floorCount,
            'floor_area'  => $floorArea,
        ]);
        $wellsResult    = $this->wells->compute(['floor_area' => $floorArea]);
        $netDepthResult = $this->netDepth->compute(['floor_count' => $floorCount]);

        $matrixOut = $matrixResult->outputs;

        // SPECIAL_STUDY_REQUIRED — accepted with technical_review_required flag
        if (($matrixOut['status'] ?? null) === ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED) {
            return ServiceSubmissionDecision::accepted(
                derivedValues: array_merge(
                    [
                        'exploration_requirement_status'    => $matrixOut['status'],
                        'exploration_special_study_reason'  => $matrixOut['reason'] ?? null,
                        'minimum_exploration_point_count'   => null,
                        'minimum_total_depth_lm'            => null,
                        'technical_review_required'         => true,
                    ],
                    $this->meetingDerivedValues($wellsResult->outputs, $netDepthResult->outputs),
                ),
                warnings: ['SRV-001 SPECIAL_STUDY_REQUIRED — downstream flow must route to JEA technical review.'],
                calculationSnapshots: [
                    $matrixResult->toSnapshotPayload(),
                    $wellsResult->toSnapshotPayload(),
                    $netDepthResult->toSnapshotPayload(),
                ],
            );
        }

        // CALCULATED path — enforce minimum + persist derived values
        $minPts   = (int) $matrixOut['minimum_exploration_point_count'];
        $minDepth = (int) $matrixOut['minimum_total_depth_lm'];

        if ($actualPts < $minPts) {
            return ServiceSubmissionDecision::rejected([
                'actual_exploration_point_count' => [
                    sprintf(
                        'عدد نقاط الاستكشاف المنفذة (%d) أقل من الحد الأدنى المطلوب (%d) وفق جدول ص.230-231.',
                        $actualPts,
                        $minPts,
                    ),
                ],
            ]);
        }

        return ServiceSubmissionDecision::accepted(
            derivedValues: array_merge(
                [
                    'exploration_requirement_status'  => $matrixOut['status'],
                    'minimum_exploration_point_count' => $minPts,
                    'minimum_total_depth_lm'          => $minDepth,
                    'technical_review_required'       => false,
                ],
                $this->meetingDerivedValues($wellsResult->outputs, $netDepthResult->outputs),
            ),
            calculationSnapshots: [
                $matrixResult->toSnapshotPayload(),
                $wellsResult->toSnapshotPayload(),
                $netDepthResult->toSnapshotPayload(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $wellsOutputs
     * @param array<string, mixed> $netDepthOutputs
     * @return array<string, mixed>
     */
    private function meetingDerivedValues(array $wellsOutputs, array $netDepthOutputs): array
    {
        $out = [];
        if (($wellsOutputs['status'] ?? null) === 'CALCULATED') {
            $out['meeting_wells_count'] = $wellsOutputs['wells'] ?? null;
            $out['meeting_wells_band']  = $wellsOutputs['band']  ?? null;
        }
        if (($netDepthOutputs['status'] ?? null) === 'CALCULATED') {
            $out['meeting_net_depth_third_m']      = $netDepthOutputs['third_m']      ?? null;
            $out['meeting_net_depth_two_thirds_m'] = $netDepthOutputs['two_thirds_m'] ?? null;
            $out['meeting_net_depth_total_m']      = $netDepthOutputs['total_m']      ?? null;
        }
        return $out;
    }

    /**
     * @param  list<array<string,mixed>>|array<mixed>  $routing
     * @param  array<string,mixed>                     $data
     */
    private function checkRouting(array $routing, array $data): ?string
    {
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
                return (string) ($rule['message_ar']
                    ?? "المشاريع الحكومية تُقدَّم عبر خدمة {$rule['target']}.");
            }
        }
        return null;
    }

    private function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
