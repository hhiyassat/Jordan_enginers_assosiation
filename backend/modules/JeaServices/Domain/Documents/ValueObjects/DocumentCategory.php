<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\ValueObjects;

/**
 * TD-06 · Structural document-category vocabulary for SRV-001.
 *
 * The 13 categories the SRV-001 mandate lists. These are structural
 * — they classify a `DocumentMetadata` for downstream flow decisions,
 * but they do NOT specify size limits, MIME requirements, or any
 * other production policy (OD-24 blocks those).
 */
final class DocumentCategory
{
    public const SIGNED_CONTRACT             = 'SIGNED_CONTRACT';
    public const LAND_REGISTRATION_DOCUMENT  = 'LAND_REGISTRATION_DOCUMENT';
    public const TITLE_DEED_QUSHAN           = 'TITLE_DEED_QUSHAN';
    public const ZONING_DOCUMENT             = 'ZONING_DOCUMENT';
    public const LAND_RELATED_EVIDENCE       = 'LAND_RELATED_EVIDENCE';
    public const RELATIONSHIP_PROOF          = 'RELATIONSHIP_PROOF';
    public const EXEMPTION_EVIDENCE          = 'EXEMPTION_EVIDENCE';
    public const JUSTIFICATION_LETTER        = 'JUSTIFICATION_LETTER';
    public const BOREHOLE_IMAGE              = 'BOREHOLE_IMAGE';
    public const GENERAL_SITE_IMAGE          = 'GENERAL_SITE_IMAGE';
    public const OPTIONAL_ADDITIONAL_MEDIA   = 'OPTIONAL_ADDITIONAL_MEDIA';
    public const TECHNICAL_REPORT            = 'TECHNICAL_REPORT';
    public const CLEARANCE_LETTER            = 'CLEARANCE_LETTER';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SIGNED_CONTRACT,
            self::LAND_REGISTRATION_DOCUMENT,
            self::TITLE_DEED_QUSHAN,
            self::ZONING_DOCUMENT,
            self::LAND_RELATED_EVIDENCE,
            self::RELATIONSHIP_PROOF,
            self::EXEMPTION_EVIDENCE,
            self::JUSTIFICATION_LETTER,
            self::BOREHOLE_IMAGE,
            self::GENERAL_SITE_IMAGE,
            self::OPTIONAL_ADDITIONAL_MEDIA,
            self::TECHNICAL_REPORT,
            self::CLEARANCE_LETTER,
        ];
    }

    public static function isValid(string $v): bool
    {
        return in_array($v, self::all(), true);
    }

    /**
     * Categories whose acceptance triggers the signed-contract lock.
     * @return list<string>
     */
    public static function locksApplicationFields(): array
    {
        return [self::SIGNED_CONTRACT];
    }
}
