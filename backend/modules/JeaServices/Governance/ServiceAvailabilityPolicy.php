<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Models\ServiceDefinition;

/**
 * SG-02 · Runtime availability policy.
 *
 * Reconciles the legacy `status` column and the new `publication_status`
 * column into a single typed verdict per service, per caller context.
 *
 * Preference order (LENIENT default mode) documented in
 * docs/architecture/service-governance/judgments/JDG-SG02-01-availability-preference-order.md:
 *
 *   1. publication_status = RETIRED   → historical view only
 *   2. publication_status = SUSPENDED → hidden for applicants, visible for admin
 *   3. publication_status = PUBLISHED → visible
 *   4. legacy status='active' + publication_status=NOT_PUBLISHED
 *                                    → visible with AVAIL_LEGACY_STATUS_FALLBACK
 *   5. legacy status!='active' + publication_status=NOT_PUBLISHED
 *                                    → hidden unless admin
 */
final class ServiceAvailabilityPolicy
{
    public const MODE_LENIENT = 'LENIENT';
    public const MODE_STRICT  = 'STRICT'; // reserved for post-versioning enforcement

    public function __construct(
        private readonly string $mode = self::MODE_LENIENT,
    ) {
    }

    public function evaluate(ServiceDefinition $service, bool $actorIsAdmin = false): ServiceAvailabilityVerdict
    {
        $publication = $service->publication_status ?? 'NOT_PUBLISHED';
        $legacy      = $service->status ?? 'inactive';
        $reasons     = [];

        // Rule 1: RETIRED — historical view only
        if ($publication === 'RETIRED') {
            $reasons[] = ServiceAvailabilityVerdict::AVAIL_HIDDEN_RETIRED;
            return ServiceAvailabilityVerdict::forService(
                $service,
                catalogVisible: $actorIsAdmin,
                applicationCreationAllowed: false,
                submissionAllowed: false,
                paymentAllowed: false,
                certificateAllowed: true, // historical certificates remain issuable
                reasonCodes: $actorIsAdmin
                    ? array_merge($reasons, [ServiceAvailabilityVerdict::AVAIL_ALLOWED_ADMIN_INSPECTION, ServiceAvailabilityVerdict::AVAIL_ALLOWED_HISTORICAL_ONLY])
                    : array_merge($reasons, [ServiceAvailabilityVerdict::AVAIL_ALLOWED_HISTORICAL_ONLY]),
            );
        }

        // Rule 2: SUSPENDED — hidden for applicants, visible for admin
        if ($publication === 'SUSPENDED') {
            $reasons[] = ServiceAvailabilityVerdict::AVAIL_HIDDEN_SUSPENDED_FOR_APPLICANT;
            return ServiceAvailabilityVerdict::forService(
                $service,
                catalogVisible: $actorIsAdmin,
                applicationCreationAllowed: false,
                submissionAllowed: false, // in-flight applications are the historical case; SG-03 will decide
                paymentAllowed: false,
                certificateAllowed: true,
                reasonCodes: $actorIsAdmin
                    ? array_merge($reasons, [ServiceAvailabilityVerdict::AVAIL_ALLOWED_ADMIN_INSPECTION])
                    : $reasons,
            );
        }

        // Rule 3: PUBLISHED — visible + all operations allowed unless placeholder-fee flag flips
        if ($publication === 'PUBLISHED') {
            return $this->publishedVerdict($service);
        }

        // Rules 4 & 5: publication_status = NOT_PUBLISHED
        if ($legacy === 'active') {
            // Rule 4 — legacy fallback (LENIENT only)
            if ($this->mode === self::MODE_LENIENT) {
                $reasons[] = ServiceAvailabilityVerdict::AVAIL_LEGACY_STATUS_FALLBACK;
                return ServiceAvailabilityVerdict::forService(
                    $service,
                    catalogVisible: true,
                    applicationCreationAllowed: true,
                    submissionAllowed: true,
                    paymentAllowed: true,
                    certificateAllowed: true,
                    reasonCodes: $reasons,
                );
            }
            // STRICT: fallback disabled — treat as hidden
        }

        // Rule 5 — hidden except for admin inspection
        $reasons[] = ServiceAvailabilityVerdict::AVAIL_HIDDEN_NOT_PUBLISHED;
        if ($legacy !== 'active') {
            $reasons[] = ServiceAvailabilityVerdict::AVAIL_BLOCKED_LEGACY_STATUS_INACTIVE;
        }
        return ServiceAvailabilityVerdict::forService(
            $service,
            catalogVisible: $actorIsAdmin,
            applicationCreationAllowed: false,
            submissionAllowed: false,
            paymentAllowed: false,
            certificateAllowed: false,
            reasonCodes: $actorIsAdmin
                ? array_merge($reasons, [ServiceAvailabilityVerdict::AVAIL_ALLOWED_ADMIN_INSPECTION])
                : $reasons,
        );
    }

    private function publishedVerdict(ServiceDefinition $service): ServiceAvailabilityVerdict
    {
        $reasons = [];

        // Even a PUBLISHED service can be blocked by placeholder markers if an
        // admin somehow reverted the schema after publication (defensive check).
        $placeholderFee      = $this->hasPlaceholderFee($service);
        $placeholderWorkflow = $this->hasPlaceholderWorkflow($service);
        $effectiveFuture     = $service->effective_from !== null && $service->effective_from->isFuture();

        if ($placeholderFee) {
            $reasons[] = ServiceAvailabilityVerdict::AVAIL_BLOCKED_PLACEHOLDER_FEE;
        }
        if ($placeholderWorkflow) {
            $reasons[] = ServiceAvailabilityVerdict::AVAIL_BLOCKED_PLACEHOLDER_WORKFLOW;
        }
        if ($effectiveFuture) {
            $reasons[] = ServiceAvailabilityVerdict::AVAIL_BLOCKED_EFFECTIVE_FROM_FUTURE;
        }
        $blocked = $placeholderFee || $placeholderWorkflow || $effectiveFuture;

        if ($blocked) {
            return ServiceAvailabilityVerdict::forService(
                $service,
                catalogVisible: false,
                applicationCreationAllowed: false,
                submissionAllowed: false,
                paymentAllowed: false,
                certificateAllowed: true,
                reasonCodes: $reasons,
            );
        }

        return ServiceAvailabilityVerdict::forService(
            $service,
            catalogVisible: true,
            applicationCreationAllowed: true,
            submissionAllowed: true,
            paymentAllowed: true,
            certificateAllowed: true,
            reasonCodes: [ServiceAvailabilityVerdict::AVAIL_OK],
        );
    }

    private function hasPlaceholderFee(ServiceDefinition $service): bool
    {
        $fee = $service->schema['fee'] ?? null;
        if (!is_array($fee)) {
            return true;
        }
        if (($fee['type'] ?? null) === 'fixed' && (float) ($fee['amount'] ?? 0) === 0.0) {
            return true;
        }
        if (($fee['type'] ?? null) === 'fixed'
            && (float) ($fee['amount'] ?? 0) === 50000.0
            && isset($fee['source']) && str_contains((string) $fee['source'], 'JORD-85 admin-default')) {
            return true;
        }
        return false;
    }

    private function hasPlaceholderWorkflow(ServiceDefinition $service): bool
    {
        $stages = $service->schema['workflow']['stages'] ?? [];
        if (!is_array($stages) || $stages === []) {
            return true;
        }
        return count($stages) === 1 && ($stages[0]['id'] ?? null) === 'placeholder_review';
    }
}
