<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * Srv001Guard — SRV-001-specific server-side submission gates.
 *
 * Wired via ServiceSubmissionGuardRegistry (see the module provider);
 * ApplicationController never mentions the SRV-001 service code. Runs
 * after SchemaValidator (field + document required checks) and before
 * the generic capacity / sanction gates.
 *
 * Enforces three rules that must not be bypassable via a direct API call:
 *
 *   1. project_sector routing — if the applicant selected "حكومي" on
 *      an SRV-001 submission, block the submission and route them to
 *      SRV-006. Reads the declarative routing block from
 *      schema.workflow.routing so a schema-side change is picked up
 *      automatically.
 *
 *   2. Exploration-point matrix — when the (floor_count, floor_area)
 *      cell resolves to CALCULATED, the applicant-entered
 *      actual_exploration_point_count must be >= the manual's
 *      minimum. Rejects otherwise.
 *
 *   3. SPECIAL_STUDY_REQUIRED — when the cell falls outside the
 *      ordinary matrix (area > 1200, floor_count >= 9, out-of-scope
 *      cases per directive §8), do not attempt to auto-approve.
 *      Attach a technical_review_required flag to the application
 *      data so downstream stages route to JEA technical review
 *      instead of the ordinary flow.
 *
 * Returns [] on pass; associative field_id => Arabic message on fail.
 * The controller maps [] to continue, non-empty to a 422 response.
 */
final class Srv001Guard implements ServiceSubmissionGuard
{
    public const SERVICE_CODE = 'SRV-001';

    /**
     * @return array<string, string>
     */
    public function validate(Application $app): array
    {
        // Safety net when someone bypasses the registry and calls the
        // guard directly on a different service; the registry itself
        // already scopes by service code.
        $service = $app->serviceDefinition;
        if (! $service || $service->code !== self::SERVICE_CODE) {
            return [];
        }

        $data   = is_array($app->data) ? $app->data : [];
        $errors = [];

        // Rule 1 — government routing. Reads schema.workflow.routing so
        // schema authors don't need to touch this file to change the target.
        $routingError = $this->checkRouting($service->schema['workflow']['routing'] ?? [], $data);
        if ($routingError !== null) {
            $errors['project_sector'] = $routingError;
            // Routing is a hard stop — don't attempt matrix checks on a
            // submission that's about to be redirected.
            return $errors;
        }

        // Rule 2 + 3 — exploration matrix.
        $floorCount = $this->intOrNull($data['floor_count'] ?? null);
        $floorArea  = $this->floatOrNull($data['floor_area'] ?? null);
        $actualPts  = $this->intOrNull($data['actual_exploration_point_count'] ?? null);

        // SchemaValidator already enforced required=true on all three;
        // if any is still null here the schema was misauthored — treat
        // as a submission blocker instead of silently passing.
        if ($floorCount === null || $floorArea === null || $actualPts === null) {
            $errors['actual_exploration_point_count'] =
                'يتعذَّر التحقق من عدد نقاط الاستكشاف: بيانات الطوابق/المساحة غير مكتملة.';
            return $errors;
        }

        $matrix = ExplorationRequirementMatrix::compute($floorCount, $floorArea);

        if ($matrix['status'] === ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED) {
            // Not a submission error — but we tag the application so the
            // downstream workflow routes to JEA technical review rather
            // than the ordinary auto-approval path. WorkflowEngine reads
            // this flag when picking the next stage's approvers.
            $app->data = array_merge($data, [
                'exploration_requirement_status' => $matrix['status'],
                'exploration_special_study_reason' => $matrix['reason'] ?? null,
                'minimum_exploration_point_count' => null,
                'minimum_total_depth_lm' => null,
                'technical_review_required' => true,
            ]);
            $app->save();
            return []; // pass — special-study cases are allowed to submit
        }

        // CALCULATED path — persist derived values + enforce minimum.
        $minPts   = (int) $matrix['minimum_exploration_point_count'];
        $minDepth = (int) $matrix['minimum_total_depth_lm'];

        if ($actualPts < $minPts) {
            $errors['actual_exploration_point_count'] = sprintf(
                'عدد نقاط الاستكشاف المنفذة (%d) أقل من الحد الأدنى المطلوب (%d) وفق جدول ص.230-231.',
                $actualPts,
                $minPts,
            );
            return $errors;
        }

        // Persist the calculated minimums so the reviewer + certificate
        // both see the exact matrix cell that gated approval.
        $app->data = array_merge($data, [
            'exploration_requirement_status'  => $matrix['status'],
            'minimum_exploration_point_count' => $minPts,
            'minimum_total_depth_lm'          => $minDepth,
            'technical_review_required'       => false,
        ]);
        $app->save();

        return [];
    }

    /**
     * @param  list<array<string,mixed>>|array<mixed>  $routing
     * @param  array<string,mixed>                     $data
     */
    private function checkRouting(array $routing, array $data): ?string
    {
        foreach ($routing as $rule) {
            if (! is_array($rule) || ($rule['action'] ?? null) !== 'ROUTE_TO_SERVICE') {
                continue;
            }
            $when = $rule['when'] ?? [];
            if (! is_array($when) || empty($when)) {
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
