<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Documents;

use Modules\JeaServices\Domain\Documents\SignedContractLockPolicy;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentCategory;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;
use PHPUnit\Framework\TestCase;

class SignedContractLockTest extends TestCase
{
    // (21) signed-contract lock — lock is active once contract accepted.
    public function test_lock_activates_once_signed_contract_is_accepted(): void
    {
        $policy = new SignedContractLockPolicy();
        $this->assertFalse($policy->isLocked([]));
        $this->assertFalse($policy->isLocked([$this->doc(DocumentMetadata::VALIDATION_PENDING)]),
            'pending signed contract must not activate the lock');
        $this->assertTrue($policy->isLocked([$this->doc(DocumentMetadata::VALIDATION_ACCEPTED)]));
    }

    // (22) protected-field predicate is narrow, not the broader legal matrix.
    public function test_only_documented_narrow_field_set_is_protected(): void
    {
        $policy = new SignedContractLockPolicy();
        foreach (['contract_owner_name', 'tax_number', 'national_number'] as $f) {
            $this->assertTrue($policy->isProtectedField($f));
        }
        foreach (['comment_text', 'floor_area', 'random_field'] as $f) {
            $this->assertFalse($policy->isProtectedField($f),
                "TD-06 must NOT invent a broader legal-edit matrix — {$f} must not be protected");
        }
    }

    private function doc(string $validation): DocumentMetadata
    {
        return new DocumentMetadata(
            documentId:           'sc-1',
            applicationId:        10,
            category:             DocumentCategory::SIGNED_CONTRACT,
            originalFilename:     'c.pdf',
            declaredMime:         'application/pdf',
            detectedMime:         'application/pdf',
            fileSizeBytes:        100,
            checksumSha256:       '2222222222222222222222222222222222222222222222222222222222222222',
            storageKey:           'k/sc',
            documentVersion:      1,
            uploadActorId:        1,
            uploadTimestamp:      '2026-08-02T00:00:00Z',
            validationStatus:     $validation,
            quarantineStatus:     DocumentMetadata::QUARANTINE_NONE,
            malwareScanStatus:    DocumentMetadata::SCAN_CLEAN,
            sourceClassification: 'test',
        );
    }
}
