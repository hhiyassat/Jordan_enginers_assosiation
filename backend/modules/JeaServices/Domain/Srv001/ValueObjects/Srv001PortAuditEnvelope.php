<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

use InvalidArgumentException;

/**
 * TD-05 · Audit envelope carrying every field required to record a
 * port-mediated eligibility / external decision safely.
 *
 * Every port return value carries one of these envelopes. The
 * envelope MUST NOT contain: credentials, tokens, personal payloads,
 * or vendor response objects. It DOES contain:
 *
 *   • correlationId — request-scoped id (already in the app; carried
 *                     across the port boundary so the receiver can
 *                     stitch audit trails).
 *   • providerId    — free-form identifier for the port implementation
 *                     (e.g., 'oracle', 'in-memory-fake', 'sandbox').
 *   • sourceKind    — one of FAKE / SANDBOX / CACHE / MANUAL_EVIDENCE
 *                     / LIVE — allows downstream to filter its audit
 *                     view by evidence provenance.
 *   • responseClassification — provider-side classification tag
 *                     (HTTP status, payload category, timeout, etc.
 *                     — string, no vendor payload).
 *   • timestamp     — ISO-8601 timestamp (string form for immutability
 *                     across serialisation).
 *   • sourceStatus  — how well the port trusts the underlying source:
 *                     APPROVED / PROVISIONAL / DRAFT / UNKNOWN.
 *   • blockingOd    — optional Open Decision id when the outcome is
 *                     blocked by an unresolved decision.
 *   • reasonCodes   — machine-readable code list; may be empty.
 */
final class Srv001PortAuditEnvelope
{
    public const KIND_FAKE            = 'FAKE';
    public const KIND_SANDBOX         = 'SANDBOX';
    public const KIND_CACHE           = 'CACHE';
    public const KIND_MANUAL_EVIDENCE = 'MANUAL_EVIDENCE';
    public const KIND_LIVE            = 'LIVE';

    /** @return list<string> */
    public static function allSourceKinds(): array
    {
        return [
            self::KIND_FAKE,
            self::KIND_SANDBOX,
            self::KIND_CACHE,
            self::KIND_MANUAL_EVIDENCE,
            self::KIND_LIVE,
        ];
    }

    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $correlationId,
        public readonly string $providerId,
        public readonly string $sourceKind,
        public readonly string $responseClassification,
        public readonly string $timestamp,
        public readonly string $sourceStatus,
        public readonly ?string $blockingOd = null,
        public readonly array $reasonCodes = [],
    ) {
        if (! in_array($sourceKind, self::allSourceKinds(), true)) {
            throw new InvalidArgumentException("Unknown sourceKind: {$sourceKind}");
        }
        if ($correlationId === '' || $providerId === '' || $timestamp === '' || $responseClassification === '') {
            throw new InvalidArgumentException('Audit envelope required string fields must be non-empty');
        }
        // Defensive: audit envelope must NEVER carry secret-shaped
        // keys. This is a construction-time guard against accidental
        // token leakage into audit_logs.extra.
        foreach ($reasonCodes as $code) {
            $lower = strtolower($code);
            if (str_contains($lower, 'password') || str_contains($lower, 'token=') || str_contains($lower, 'bearer ')) {
                throw new InvalidArgumentException('Audit envelope reasonCode looks credential-shaped — refusing.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toAuditExtras(): array
    {
        return [
            'correlation_id'          => $this->correlationId,
            'provider_id'             => $this->providerId,
            'source_kind'             => $this->sourceKind,
            'response_classification' => $this->responseClassification,
            'timestamp'               => $this->timestamp,
            'source_status'           => $this->sourceStatus,
            'blocking_od'             => $this->blockingOd,
            'reason_codes'            => $this->reasonCodes,
        ];
    }
}
