<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Documents;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Documents\ValueObjects\AttachmentLimitPolicy;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentCategory;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;
use PHPUnit\Framework\TestCase;

class DocumentMetadataTest extends TestCase
{
    private const VALID_SHA = '0000000000000000000000000000000000000000000000000000000000000000';

    // (1) authorized attachment registration.
    public function test_valid_metadata_construction_succeeds(): void
    {
        $md = $this->baseMetadata();
        $this->assertSame(DocumentCategory::SIGNED_CONTRACT, $md->category);
        $this->assertSame(1, $md->documentVersion);
        $this->assertSame(DocumentMetadata::VALIDATION_PENDING, $md->validationStatus);
    }

    // (2) unauthorized attachment registration — unknown category rejected.
    public function test_unknown_category_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DocumentMetadata(
            documentId:           'd-1',
            applicationId:        10,
            category:             'FAKE_CATEGORY',
            originalFilename:     'x.pdf',
            declaredMime:         'application/pdf',
            detectedMime:         'application/pdf',
            fileSizeBytes:        100,
            checksumSha256:       self::VALID_SHA,
            storageKey:           's/k/1',
            documentVersion:      1,
            uploadActorId:        1,
            uploadTimestamp:      '2026-08-02T00:00:00Z',
            validationStatus:     DocumentMetadata::VALIDATION_PENDING,
            quarantineStatus:     DocumentMetadata::QUARANTINE_NONE,
            malwareScanStatus:    DocumentMetadata::SCAN_PENDING,
            sourceClassification: 'test',
        );
    }

    // (3) MIME mismatch detection.
    public function test_mime_mismatch_is_flagged_when_declared_and_detected_differ(): void
    {
        $md = $this->baseMetadata(declared: 'application/pdf', detected: 'application/x-msdownload');
        $this->assertTrue($md->mimeMismatchDetected());
    }

    // (4) magic-byte mismatch — same semantic; adapters call this.
    public function test_mime_match_returns_false_when_no_detected_mime(): void
    {
        $md = $this->baseMetadata(declared: 'application/pdf', detected: null);
        $this->assertFalse($md->mimeMismatchDetected(),
            'null detected mime cannot decide match — call the port');
    }

    // (5) checksum persistence — required 64-hex-char SHA-256.
    public function test_construction_rejects_short_or_malformed_checksum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DocumentMetadata(
            documentId:           'd-1',
            applicationId:        10,
            category:             DocumentCategory::SIGNED_CONTRACT,
            originalFilename:     'x.pdf',
            declaredMime:         'application/pdf',
            detectedMime:         'application/pdf',
            fileSizeBytes:        100,
            checksumSha256:       'too-short',
            storageKey:           's/k/1',
            documentVersion:      1,
            uploadActorId:        1,
            uploadTimestamp:      '2026-08-02T00:00:00Z',
            validationStatus:     DocumentMetadata::VALIDATION_PENDING,
            quarantineStatus:     DocumentMetadata::QUARANTINE_NONE,
            malwareScanStatus:    DocumentMetadata::SCAN_PENDING,
            sourceClassification: 'test',
        );
    }

    // (6) quarantine state.
    public function test_metadata_carries_quarantine_state_verbatim(): void
    {
        $md = $this->baseMetadata(quarantine: DocumentMetadata::QUARANTINE_HELD);
        $this->assertSame(DocumentMetadata::QUARANTINE_HELD, $md->quarantineStatus);
    }

    // (7) scan-pending state.
    public function test_metadata_carries_scan_pending(): void
    {
        $md = $this->baseMetadata();
        $this->assertSame(DocumentMetadata::SCAN_PENDING, $md->malwareScanStatus);
    }

    // (8) immutable document version — no setters.
    public function test_document_metadata_is_immutable_no_property_setters(): void
    {
        $md = $this->baseMetadata();
        $refl = new \ReflectionClass($md);
        foreach ($refl->getProperties() as $p) {
            $this->assertTrue($p->isReadOnly(), "{$p->getName()} must be readonly");
        }
    }

    // (9) replacement creates a NEW version (documentVersion + 1).
    public function test_withReplacementVersion_returns_new_instance_with_incremented_version(): void
    {
        $md = $this->baseMetadata();
        $replacement = $md->withReplacementVersion(
            newDocumentId:      'd-2',
            newChecksumSha256:  self::VALID_SHA,
            newStorageKey:      's/k/2',
            newFileSizeBytes:   200,
            uploadActorId:      2,
            uploadTimestamp:    '2026-08-02T00:01:00Z',
        );
        $this->assertSame(1, $md->documentVersion, 'original is unchanged (immutability)');
        $this->assertSame(2, $replacement->documentVersion);
        $this->assertSame(DocumentMetadata::VALIDATION_PENDING, $replacement->validationStatus,
            'replacement resets validation to PENDING');
        $this->assertSame(DocumentMetadata::SCAN_PENDING, $replacement->malwareScanStatus);
    }

    // (10) unresolved OD-24 limit not used as production default.
    public function test_attachment_limit_policy_is_blocked_when_configuration_not_published(): void
    {
        $policy   = new AttachmentLimitPolicy(configurationPublished: false);
        $decision = $policy->resolveLimit(DocumentCategory::SIGNED_CONTRACT);
        $this->assertFalse($decision->allowed);
        $this->assertNull($decision->limitBytes);
        $this->assertContains('OD-24_UNRESOLVED', $decision->reasonCodes);
    }

    // (11) attachment-limit policy: published category returns the limit.
    public function test_attachment_limit_returns_published_bytes_for_covered_category(): void
    {
        $policy   = (new AttachmentLimitPolicy())->withPublishedLimit(DocumentCategory::TECHNICAL_REPORT, 10_000_000);
        $decision = $policy->resolveLimit(DocumentCategory::TECHNICAL_REPORT);
        $this->assertTrue($decision->allowed);
        $this->assertSame(10_000_000, $decision->limitBytes);
    }

    // (12) attachment-limit policy: published for one category but not another → other still blocked.
    public function test_attachment_limit_still_blocked_for_uncovered_category_even_after_partial_publication(): void
    {
        $policy   = (new AttachmentLimitPolicy())->withPublishedLimit(DocumentCategory::TECHNICAL_REPORT, 10_000_000);
        $decision = $policy->resolveLimit(DocumentCategory::SIGNED_CONTRACT);
        $this->assertFalse($decision->allowed);
        $this->assertContains('OD-24_UNRESOLVED_FOR_SIGNED_CONTRACT', $decision->reasonCodes);
    }

    // (13) individual borehole-image structural association.
    public function test_borehole_image_category_is_recognized(): void
    {
        $md = $this->baseMetadata(category: DocumentCategory::BOREHOLE_IMAGE);
        $this->assertSame(DocumentCategory::BOREHOLE_IMAGE, $md->category);
    }

    // (14) general-site-image structural association.
    public function test_general_site_image_category_is_recognized(): void
    {
        $md = $this->baseMetadata(category: DocumentCategory::GENERAL_SITE_IMAGE);
        $this->assertSame(DocumentCategory::GENERAL_SITE_IMAGE, $md->category);
    }

    // (15) file bytes absent from Domain entities — VO has no bytes field.
    public function test_document_metadata_never_carries_file_bytes(): void
    {
        $md    = $this->baseMetadata();
        $props = get_object_vars($md);
        $this->assertArrayNotHasKey('fileBytes',    $props);
        $this->assertArrayNotHasKey('rawContent',   $props);
        $this->assertArrayNotHasKey('base64Payload', $props);
    }

    // (16) all 13 categories enumerated.
    public function test_document_category_enum_contains_all_thirteen_mandated_categories(): void
    {
        $this->assertCount(13, DocumentCategory::all());
        foreach ([
            DocumentCategory::SIGNED_CONTRACT,
            DocumentCategory::LAND_REGISTRATION_DOCUMENT,
            DocumentCategory::TITLE_DEED_QUSHAN,
            DocumentCategory::ZONING_DOCUMENT,
            DocumentCategory::LAND_RELATED_EVIDENCE,
            DocumentCategory::RELATIONSHIP_PROOF,
            DocumentCategory::EXEMPTION_EVIDENCE,
            DocumentCategory::JUSTIFICATION_LETTER,
            DocumentCategory::BOREHOLE_IMAGE,
            DocumentCategory::GENERAL_SITE_IMAGE,
            DocumentCategory::OPTIONAL_ADDITIONAL_MEDIA,
            DocumentCategory::TECHNICAL_REPORT,
            DocumentCategory::CLEARANCE_LETTER,
        ] as $c) {
            $this->assertTrue(DocumentCategory::isValid($c));
        }
    }

    // helpers
    private function baseMetadata(
        string $category = DocumentCategory::SIGNED_CONTRACT,
        string $declared = 'application/pdf',
        ?string $detected = 'application/pdf',
        string $quarantine = DocumentMetadata::QUARANTINE_NONE,
    ): DocumentMetadata {
        return new DocumentMetadata(
            documentId:           'd-1',
            applicationId:        10,
            category:             $category,
            originalFilename:     'x.pdf',
            declaredMime:         $declared,
            detectedMime:         $detected,
            fileSizeBytes:        100,
            checksumSha256:       self::VALID_SHA,
            storageKey:           's/k/1',
            documentVersion:      1,
            uploadActorId:        1,
            uploadTimestamp:      '2026-08-02T00:00:00Z',
            validationStatus:     DocumentMetadata::VALIDATION_PENDING,
            quarantineStatus:     $quarantine,
            malwareScanStatus:    DocumentMetadata::SCAN_PENDING,
            sourceClassification: 'test',
        );
    }
}
