<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Support\ArabicNormalizer;

/**
 * CadastralConflictGuard — STK-2026-07-27-CC-001.
 *
 * Prevents two engineering offices from submitting applications for the
 * same cadastral triple (basin_number, parcel_number, basin_or_location_name).
 *
 * Behavior:
 *   • In scope only when the service schema declares all three cadastral
 *     fields (via schema.fields[].id). Services without cadastral fields
 *     (CERT-*, DEC-*, ENG-*, FIN-*, MSC-*) pass through unchanged.
 *   • Rejects submission if any OTHER organization's application has the
 *     same normalized triple and its status is one of the "committed"
 *     statuses: submitted, under_review, modifications_requested, approved,
 *     certificate_issued. Draft + rejected are excluded — drafts have not
 *     committed, and rejected applications don't own the parcel.
 *   • Same-organization prior applications DO NOT block a new submission
 *     from that same organization. The rule is anti-collision between
 *     DIFFERENT offices, not anti-duplicate within one office.
 *   • Arabic text matching uses ArabicNormalizer — cosmetic differences
 *     in diacritics / alef variants / whitespace do not defeat the check.
 *
 * Deferred to CC-002 (OwnerMatchClearanceGuard):
 *   • Owner-name match → conditional clearance + discharge document
 *     requirement. This guard alone REJECTS on triple; CC-002 will run
 *     AFTER this guard and only fires when this guard passes (i.e. no
 *     absolute conflict) but there is a prior owner-matched record.
 *
 * Open governance items surfaced in docs/architecture/cross-cutting-submission-pipeline.md:
 *   • UNQ-015: exact-vs-normalized matching — default is normalized (this file).
 *   • UNQ-019: prior-office document issuance — not addressed here.
 */
final class CadastralConflictGuard implements CrossCuttingSubmissionGuard
{
    private const CADASTRAL_FIELD_IDS = [
        'basin_number',
        'parcel_number',
        'basin_or_location_name',
    ];

    /**
     * Application statuses that "commit" a parcel to the applying office.
     * A prior application in any of these blocks a different office from
     * submitting on the same triple.
     */
    private const CONFLICTING_STATUSES = [
        Application::STATUS_SUBMITTED,
        Application::STATUS_UNDER_REVIEW,
        Application::STATUS_MODIFICATIONS_REQUESTED,
        Application::STATUS_APPROVED,
        Application::STATUS_CERTIFICATE_ISSUED,
    ];

    public function validate(Application $app): array
    {
        // 1. In-scope check — service must declare all three cadastral fields.
        $service = $app->serviceDefinition;
        if ($service === null) {
            return [];
        }
        $schemaFieldIds = array_column($service->schema['fields'] ?? [], 'id');
        foreach (self::CADASTRAL_FIELD_IDS as $required) {
            if (! in_array($required, $schemaFieldIds, true)) {
                return []; // service is not cadastral-scoped
            }
        }

        // 2. Extract the applicant's cadastral triple from submitted data.
        $data = is_array($app->data) ? $app->data : [];
        $basin = $data['basin_number'] ?? null;
        $parcel = $data['parcel_number'] ?? null;
        $basinName = $data['basin_or_location_name'] ?? null;

        // If any triple component is missing, SchemaValidator already caught
        // it (fields are marked required in the pilot schema). We defer to
        // SchemaValidator's error rather than double-erroring here.
        if ($basin === null || $parcel === null || $basinName === null
            || $basin === '' || $parcel === '' || $basinName === '') {
            return [];
        }

        // 3. Fetch candidate prior applications in a committed status from
        //    OTHER organizations, then compare normalized triples.
        //
        //    IMPORTANT: this query MUST bypass the OrganizationScope global
        //    scope that BelongsToOrganization installs on Application.
        //    Under a Sanctum-authenticated request context, Application::query()
        //    is auto-filtered to the caller's own organization — which would
        //    hide the cross-org applications we're trying to detect and make
        //    the cadastral-conflict check silently pass. `withoutOrgScope()`
        //    exposes the full applications table so the cross-org invariant
        //    can be enforced.
        //
        //    We can't push the normalized comparison into SQL cleanly (it's
        //    Unicode text-level manipulation) so we filter the candidate
        //    set on organization + status in SQL and normalize in PHP.
        //    Basin_number and parcel_number are typically numeric strings —
        //    push those as exact matches to shrink the candidate set. The
        //    basin_or_location_name is where normalization matters most.
        $candidates = Application::withoutOrgScope()
            ->where('organization_id', '!=', $app->organization_id)
            ->whereIn('status', self::CONFLICTING_STATUSES)
            ->where('id', '!=', $app->id)
            ->whereRaw("json_extract(data, '$.basin_number') = ?", [$basin])
            ->whereRaw("json_extract(data, '$.parcel_number') = ?", [$parcel])
            ->get(['id', 'data', 'organization_id']);

        $normalizedName = ArabicNormalizer::normalize($basinName);
        foreach ($candidates as $candidate) {
            $cData = is_array($candidate->data) ? $candidate->data : [];
            $candidateName = $cData['basin_or_location_name'] ?? null;
            if ($candidateName === null) {
                continue;
            }
            if (ArabicNormalizer::normalize($candidateName) === $normalizedName) {
                // Match found → block this submission.
                return [
                    'basin_number' => 'هذه القطعة والحوض مسجَّلان سابقاً لمكتب هندسي آخر. لا يمكن التقديم قبل تسوية الوضع مع المكتب السابق أو النقابة.',
                ];
            }
        }

        return [];
    }
}
