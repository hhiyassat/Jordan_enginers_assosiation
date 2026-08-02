<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Cutover;

/**
 * TD-10 · Machine-checkable cutover checklist.
 *
 * Every checklist item is a named boolean. `evaluate($state)`
 * returns a verdict: CUTOVER_READY / CUTOVER_READY_WITH_SIGNED_EXCEPTIONS
 * / NOT_CUTOVER_READY based on which items are truthy in the
 * supplied `$state` map.
 *
 * FAIL-CLOSED: absent keys default to false — any missing item
 * blocks readiness.
 */
final class CutoverChecklist
{
    public const VERDICT_READY               = 'CUTOVER_READY';
    public const VERDICT_READY_WITH_EXCEPTIONS = 'CUTOVER_READY_WITH_SIGNED_EXCEPTIONS';
    public const VERDICT_NOT_READY           = 'NOT_CUTOVER_READY';

    /** @return list<string> */
    public static function items(): array
    {
        return [
            'signed_srs_baseline_2_0',
            'signed_od_closures',
            'approved_detailed_rtm',
            'approved_workflow_version',
            'approved_calculation_rule_versions',
            'approved_financial_rule_versions',
            'approved_attachment_limits',
            'oracle_contract',
            'dls_contract',
            'bura_contracts',
            'storage_adapter',
            'malware_scanner',
            'payment_gateway',
            'receipt_authority',
            'certificate_authority',
            'sandbox_evidence',
            'security_review',
            'performance_evidence',
            'uat_approval',
            'migration_dry_run_approval',
            'rollback_rehearsal',
            'monitoring_readiness',
            'support_readiness',
            'publication_approval',
            'production_change_approval',
        ];
    }

    /**
     * @param array<string, bool>            $state
     * @param list<string>                   $signedExceptions
     * @return array{verdict: string, missing: list<string>, exceptions: list<string>}
     */
    public function evaluate(array $state, array $signedExceptions = []): array
    {
        $missing = [];
        foreach (self::items() as $item) {
            if (empty($state[$item])) {
                $missing[] = $item;
            }
        }
        if ($missing === []) {
            return ['verdict' => self::VERDICT_READY, 'missing' => [], 'exceptions' => []];
        }
        // Verify signed exceptions actually cover every missing item.
        $uncovered = array_values(array_diff($missing, $signedExceptions));
        if ($uncovered === []) {
            return [
                'verdict'    => self::VERDICT_READY_WITH_EXCEPTIONS,
                'missing'    => $missing,
                'exceptions' => $signedExceptions,
            ];
        }
        return [
            'verdict'    => self::VERDICT_NOT_READY,
            'missing'    => $missing,
            'exceptions' => $signedExceptions,
        ];
    }
}
