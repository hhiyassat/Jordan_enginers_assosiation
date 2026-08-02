<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

use InvalidArgumentException;

/**
 * TD-05 · FR-SS-088 internal mandatory note value object.
 *
 * STRUCTURAL only — no migration, no runtime activation. When TD-05+
 * adds the runtime consumer, this VO becomes the payload the
 * `MandatoryNotesPort` returns AND the anchor for a future Eloquent
 * model.
 *
 * A note has a scope (office | parcel) and an effect. Effect=BLOCK
 * means submission is refused while the note is active; effect=WARN
 * surfaces the text but does not block.
 *
 * IMMUTABLE.
 */
final class InternalMandatoryNote
{
    public const SCOPE_OFFICE = 'OFFICE';
    public const SCOPE_PARCEL = 'PARCEL';

    public const EFFECT_BLOCK = 'BLOCK';
    public const EFFECT_WARN  = 'WARN';

    public function __construct(
        public readonly string $noteId,
        public readonly string $scope,
        public readonly ?int $organizationId,
        public readonly ?string $basinNumber,
        public readonly ?string $parcelNumber,
        public readonly string $effect,
        public readonly string $noteTextAr,
        public readonly bool $isActive = true,
    ) {
        if ($noteId === '' || $noteTextAr === '') {
            throw new InvalidArgumentException('noteId and noteTextAr required');
        }
        if (! in_array($scope, [self::SCOPE_OFFICE, self::SCOPE_PARCEL], true)) {
            throw new InvalidArgumentException("Unknown scope: {$scope}");
        }
        if (! in_array($effect, [self::EFFECT_BLOCK, self::EFFECT_WARN], true)) {
            throw new InvalidArgumentException("Unknown effect: {$effect}");
        }
        if ($scope === self::SCOPE_OFFICE && $organizationId === null) {
            throw new InvalidArgumentException('OFFICE-scoped note requires organizationId');
        }
        if ($scope === self::SCOPE_PARCEL && ($basinNumber === null || $parcelNumber === null)) {
            throw new InvalidArgumentException('PARCEL-scoped note requires basinNumber and parcelNumber');
        }
    }

    public function blocksSubmission(): bool
    {
        return $this->isActive && $this->effect === self::EFFECT_BLOCK;
    }
}
