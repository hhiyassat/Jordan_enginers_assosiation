<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Database\Seeders\ServiceFeeDefaultsSeeder;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * SG-01 · Publication policy.
 *
 * Applies the صحة conditions and موانع defined in
 * docs/architecture/service-governance/judgments/JDG-SG01-02-publication-conditions.md.
 *
 * The policy is pure: it accepts a ServiceDefinition + actor id and returns
 * a PublicationDecision. It does not persist. Callers wire the decision
 * into a publication use case that flips publication_status.
 */
final class ServicePublicationPolicy
{
    /**
     * @param  int|null  $actorUserId  the user attempting to publish; used for maker-checker
     */
    public function evaluate(ServiceDefinition $service, ?int $actorUserId = null): PublicationDecision
    {
        $reasonCodes = [];
        $messages    = [];

        // publication_reason must exist to explain why the service is being published.
        if (empty($service->publication_reason)) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_MISSING_REASON;
            $messages[PublicationDecision::PUB_BLOCKED_MISSING_REASON] =
                'Publication requires a publication_reason.';
        }

        // Structural schema validity — check top-level keys are present.
        if (!$this->schemaStructurallyValid($service)) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_SCHEMA_STRUCTURE;
            $messages[PublicationDecision::PUB_BLOCKED_SCHEMA_STRUCTURE] =
                'Schema structure is invalid: workflow.stages, fields, documents, and fee are required.';
        }

        // Fee must not be the placeholder default (0 or 50000-with-source-marker).
        if ($this->hasPlaceholderFee($service)) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_PLACEHOLDER_FEE;
            $messages[PublicationDecision::PUB_BLOCKED_PLACEHOLDER_FEE] =
                'Fee is the seeded placeholder; a real fee must be configured before publication.';
        }

        // Workflow must not be the placeholder single-stage placeholder_review.
        if ($this->hasPlaceholderWorkflow($service)) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_PLACEHOLDER_WORKFLOW;
            $messages[PublicationDecision::PUB_BLOCKED_PLACEHOLDER_WORKFLOW] =
                'Workflow is the placeholder_review stage; a real workflow must be configured.';
        }

        // UAT must be APPROVED with a reference.
        if ($service->uat_status !== 'APPROVED') {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_MISSING_UAT;
            $messages[PublicationDecision::PUB_BLOCKED_MISSING_UAT] =
                'UAT approval is required (uat_status=APPROVED).';
        }
        if (empty($service->uat_reference)) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_MISSING_UAT_REFERENCE;
            $messages[PublicationDecision::PUB_BLOCKED_MISSING_UAT_REFERENCE] =
                'A uat_reference (signed decision reference) is required.';
        }

        // effective_from — future dates block publication until reached.
        if ($service->effective_from !== null && $service->effective_from->isFuture()) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_EFFECTIVE_FROM_FUTURE;
            $messages[PublicationDecision::PUB_BLOCKED_EFFECTIVE_FROM_FUTURE] =
                'effective_from is in the future; publication cannot occur before that date.';
        }

        // Maker-checker: publisher must not be the UAT signer.
        if ($actorUserId !== null
            && $service->uat_signed_by !== null
            && (int) $service->uat_signed_by === $actorUserId) {
            $reasonCodes[] = PublicationDecision::PUB_BLOCKED_MAKER_CHECKER;
            $messages[PublicationDecision::PUB_BLOCKED_MAKER_CHECKER] =
                'Maker-checker violation: the user who signed UAT cannot publish the same service.';
        }

        return $reasonCodes === []
            ? PublicationDecision::ok()
            : PublicationDecision::blocked($reasonCodes, $messages);
    }

    private function schemaStructurallyValid(ServiceDefinition $service): bool
    {
        $schema = $service->schema ?? [];
        if (!isset($schema['workflow']['stages']) || !is_array($schema['workflow']['stages']) || $schema['workflow']['stages'] === []) {
            return false;
        }
        if (!array_key_exists('fields', $schema) || !is_array($schema['fields'])) {
            return false;
        }
        if (!array_key_exists('documents', $schema) || !is_array($schema['documents'])) {
            return false;
        }
        if (!isset($schema['fee']) || !is_array($schema['fee'])) {
            return false;
        }
        return true;
    }

    private function hasPlaceholderFee(ServiceDefinition $service): bool
    {
        $fee = $service->schema['fee'] ?? null;
        if (!is_array($fee)) {
            return true; // absent fee also blocks
        }

        // amount=0 placeholder from ServicePlan2026Seeder::placeholderSchema
        if (($fee['type'] ?? null) === 'fixed' && (float) ($fee['amount'] ?? 0) === 0.0) {
            return true;
        }

        // 50000 JOD default from ServiceFeeDefaultsSeeder (marker in source string)
        if (($fee['type'] ?? null) === 'fixed'
            && (float) ($fee['amount'] ?? 0) === (float) ServiceFeeDefaultsSeeder::DEFAULT_AMOUNT_JOD
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
        return count($stages) === 1
            && ($stages[0]['id'] ?? null) === 'placeholder_review';
    }
}
