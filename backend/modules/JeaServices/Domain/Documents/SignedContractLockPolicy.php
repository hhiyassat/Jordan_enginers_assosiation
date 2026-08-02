<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents;

use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentCategory;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;

/**
 * TD-06 · Signed-contract lock policy.
 *
 * Once a `SIGNED_CONTRACT` document reaches `VALIDATION_ACCEPTED`,
 * legally-protected fields on the application are locked. Callers
 * (controllers, use cases, edit grants) must consult this policy
 * before permitting any mutation of the protected field set.
 *
 * INVARIANT: this policy does NOT invent a broader legal edit
 * matrix — it enforces the narrow "signed contract accepted →
 * legally protected fields locked" rule only. A broader matrix
 * requires product / legal sign-off (not TD-06 scope).
 */
final class SignedContractLockPolicy
{
    /**
     * Fields that become read-only once a signed contract is
     * accepted. Kept intentionally narrow — an over-broad list
     * would expand scope beyond what the mandate authorizes.
     *
     * @return list<string>
     */
    public function legallyProtectedFields(): array
    {
        return [
            'contract_owner_name',
            'contract_party_type',
            'contract_signed_at',
            'tax_number',
            'national_number',
        ];
    }

    /**
     * Given the current stack of documents on an application,
     * decide whether the signed-contract lock is currently active.
     *
     * @param list<DocumentMetadata> $documents
     */
    public function isLocked(array $documents): bool
    {
        foreach ($documents as $doc) {
            if (
                $doc->category === DocumentCategory::SIGNED_CONTRACT
                && $doc->validationStatus === DocumentMetadata::VALIDATION_ACCEPTED
            ) {
                return true;
            }
        }
        return false;
    }

    public function isProtectedField(string $fieldId): bool
    {
        return in_array($fieldId, $this->legallyProtectedFields(), true);
    }
}
