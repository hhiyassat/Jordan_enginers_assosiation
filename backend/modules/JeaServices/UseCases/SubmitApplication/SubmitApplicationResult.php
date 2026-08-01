<?php

declare(strict_types=1);

namespace Modules\JeaServices\UseCases\SubmitApplication;

/**
 * TD-02 · Immutable result returned by SubmitApplicationUseCase.
 *
 * When `succeeded=false`, no DB writes were performed (either the
 * decision was rejected upstream, or the transaction rolled back).
 * When `succeeded=true`, all three side effects (data merge + version
 * bind + snapshot writes) were committed atomically.
 */
final class SubmitApplicationResult
{
    /**
     * @param bool                             $succeeded
     * @param array<string, list<string>>      $rejectionErrors            field-id keyed (empty when succeeded)
     * @param ?string                          $rollbackReason             non-null when the tx rolled back (rare; caller may inspect)
     * @param string                           $versionBindingClassification  ALREADY_BOUND | BOUND | LEGACY_UNVERSIONED | NO_SERVICE_DEFINITION | NOT_ATTEMPTED
     * @param ?int                             $boundVersionId             set when classification=ALREADY_BOUND|BOUND
     * @param list<int>                        $snapshotIds                calculation_snapshots.id list (empty on failure)
     * @param bool                             $derivedValuesPersisted
     */
    public function __construct(
        public readonly bool $succeeded,
        public readonly array $rejectionErrors,
        public readonly ?string $rollbackReason,
        public readonly string $versionBindingClassification,
        public readonly ?int $boundVersionId,
        public readonly array $snapshotIds,
        public readonly bool $derivedValuesPersisted,
    ) {
    }

    /**
     * @param array<string, list<string>>  $errors
     */
    public static function rejected(array $errors): self
    {
        return new self(
            succeeded: false,
            rejectionErrors: $errors,
            rollbackReason: null,
            versionBindingClassification: 'NOT_ATTEMPTED',
            boundVersionId: null,
            snapshotIds: [],
            derivedValuesPersisted: false,
        );
    }

    /**
     * @param list<int>  $snapshotIds
     */
    public static function committed(
        string $versionBindingClassification,
        ?int $boundVersionId,
        array $snapshotIds,
        bool $derivedValuesPersisted,
    ): self {
        return new self(
            succeeded: true,
            rejectionErrors: [],
            rollbackReason: null,
            versionBindingClassification: $versionBindingClassification,
            boundVersionId: $boundVersionId,
            snapshotIds: $snapshotIds,
            derivedValuesPersisted: $derivedValuesPersisted,
        );
    }

    public static function rolledBack(string $reason): self
    {
        return new self(
            succeeded: false,
            rejectionErrors: [],
            rollbackReason: $reason,
            versionBindingClassification: 'NOT_ATTEMPTED',
            boundVersionId: null,
            snapshotIds: [],
            derivedValuesPersisted: false,
        );
    }
}
