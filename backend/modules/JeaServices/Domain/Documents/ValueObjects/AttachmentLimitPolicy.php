<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\ValueObjects;

/**
 * TD-06 · Attachment-limit policy resolver.
 *
 * OD-24 is unresolved — no signed per-category attachment limit
 * exists. Therefore:
 *
 *   HARDCODED_UNAPPROVED_LIMITS=NO
 *   DEFAULT_PRODUCTION_LIMITS=NONE
 *   LIMIT_SOURCE=VERSIONED_CONFIGURATION
 *   TARGET_LIMIT_CONFIGURATION_STATUS=UNPUBLISHED
 *
 * When a caller asks this policy for a limit and no versioned
 * configuration has been published, it MUST return
 * `LimitDecision::configurationBlocked(...)` — not a fabricated
 * default like 4 MB or 500 MB.
 *
 * Consumers building a document-upload controller must inspect the
 * decision and either surface a MANUAL_REVIEW outcome or refuse the
 * upload — they must NOT fall back to a hardcoded number.
 */
final class AttachmentLimitPolicy
{
    /** @var array<string, int> category => bytes (populated only when a signed configuration lands) */
    private array $publishedLimits = [];

    public function __construct(private readonly bool $configurationPublished = false)
    {
    }

    public function withPublishedLimit(string $category, int $bytes): self
    {
        $clone = new self(configurationPublished: true);
        $clone->publishedLimits = $this->publishedLimits;
        $clone->publishedLimits[$category] = $bytes;
        return $clone;
    }

    public function resolveLimit(string $category): AttachmentLimitDecision
    {
        if (! $this->configurationPublished) {
            return AttachmentLimitDecision::configurationBlocked(['OD-24_UNRESOLVED']);
        }
        if (! array_key_exists($category, $this->publishedLimits)) {
            return AttachmentLimitDecision::configurationBlocked(["OD-24_UNRESOLVED_FOR_{$category}"]);
        }
        return AttachmentLimitDecision::allowed($this->publishedLimits[$category]);
    }
}
