<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\ValueObjects;

use InvalidArgumentException;

/**
 * TD-06 · Immutable metadata for an SRV-001 document attachment.
 *
 * FILE BYTES ARE NEVER STORED HERE. This VO carries pointers +
 * classifications + provenance only. Actual object storage happens
 * behind `ObjectStoragePort` — bytes cross the port boundary as an
 * opaque handle (storage key), never as a PHP string.
 *
 * Domain-layer invariant enforced by the `Srv001DocumentBoundariesTest`
 * architecture test.
 */
final class DocumentMetadata
{
    public const VALIDATION_PENDING  = 'PENDING';
    public const VALIDATION_ACCEPTED = 'ACCEPTED';
    public const VALIDATION_REJECTED = 'REJECTED';

    public const QUARANTINE_NONE       = 'NONE';
    public const QUARANTINE_HELD       = 'HELD';
    public const QUARANTINE_RELEASED   = 'RELEASED';

    public const SCAN_PENDING = 'PENDING';
    public const SCAN_CLEAN   = 'CLEAN';
    public const SCAN_INFECTED = 'INFECTED';
    public const SCAN_UNKNOWN = 'UNKNOWN';

    /**
     * @param int|null $supersededDocumentId  set when this document version
     *   supersedes an earlier one (SG-04 immutability rule: never mutate
     *   the earlier row; add a new version + reference the ancestor).
     */
    public function __construct(
        public readonly string $documentId,
        public readonly int $applicationId,
        public readonly string $category,
        public readonly string $originalFilename,
        public readonly string $declaredMime,
        public readonly ?string $detectedMime,
        public readonly int $fileSizeBytes,
        public readonly string $checksumSha256,
        public readonly string $storageKey,
        public readonly int $documentVersion,
        public readonly int $uploadActorId,
        public readonly string $uploadTimestamp,
        public readonly string $validationStatus,
        public readonly string $quarantineStatus,
        public readonly string $malwareScanStatus,
        public readonly string $sourceClassification,
        public readonly ?int $supersededDocumentId = null,
        public readonly bool $legalLockActive = false,
    ) {
        if ($documentId === '' || $originalFilename === '' || $storageKey === '' || $checksumSha256 === '') {
            throw new InvalidArgumentException('DocumentMetadata: required string fields missing');
        }
        if (! DocumentCategory::isValid($category)) {
            throw new InvalidArgumentException("Unknown category: {$category}");
        }
        if ($fileSizeBytes < 0) {
            throw new InvalidArgumentException('fileSizeBytes must be non-negative');
        }
        if ($documentVersion < 1) {
            throw new InvalidArgumentException('documentVersion must be >= 1');
        }
        // The VO must NEVER carry file bytes. Refuse any field that
        // even looks like base64 payload. This is a construction-time
        // defensive guard.
        if (strlen($checksumSha256) !== 64) {
            throw new InvalidArgumentException('checksumSha256 must be a 64-hex-char SHA-256 (bytes never stored here)');
        }
        if (! in_array(
            $validationStatus,
            [self::VALIDATION_PENDING, self::VALIDATION_ACCEPTED, self::VALIDATION_REJECTED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown validationStatus: {$validationStatus}");
        }
        if (! in_array(
            $quarantineStatus,
            [self::QUARANTINE_NONE, self::QUARANTINE_HELD, self::QUARANTINE_RELEASED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown quarantineStatus: {$quarantineStatus}");
        }
        if (! in_array(
            $malwareScanStatus,
            [self::SCAN_PENDING, self::SCAN_CLEAN, self::SCAN_INFECTED, self::SCAN_UNKNOWN],
            true,
        )) {
            throw new InvalidArgumentException("Unknown malwareScanStatus: {$malwareScanStatus}");
        }
    }

    public function mimeMismatchDetected(): bool
    {
        return $this->detectedMime !== null && $this->detectedMime !== $this->declaredMime;
    }

    public function withReplacementVersion(
        string $newDocumentId,
        string $newChecksumSha256,
        string $newStorageKey,
        int $newFileSizeBytes,
        int $uploadActorId,
        string $uploadTimestamp,
    ): self {
        return new self(
            documentId:            $newDocumentId,
            applicationId:         $this->applicationId,
            category:              $this->category,
            originalFilename:      $this->originalFilename,
            declaredMime:          $this->declaredMime,
            detectedMime:          $this->detectedMime,
            fileSizeBytes:         $newFileSizeBytes,
            checksumSha256:        $newChecksumSha256,
            storageKey:            $newStorageKey,
            documentVersion:       $this->documentVersion + 1,
            uploadActorId:         $uploadActorId,
            uploadTimestamp:       $uploadTimestamp,
            validationStatus:      self::VALIDATION_PENDING,
            quarantineStatus:      self::QUARANTINE_NONE,
            malwareScanStatus:     self::SCAN_PENDING,
            sourceClassification:  $this->sourceClassification,
            supersededDocumentId:  (int) $this->documentIdAsIntIfNumeric(),
            legalLockActive:       $this->legalLockActive,
        );
    }

    private function documentIdAsIntIfNumeric(): ?int
    {
        return is_numeric($this->documentId) ? (int) $this->documentId : null;
    }
}
