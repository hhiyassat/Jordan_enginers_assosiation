<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * OwnerMatchClearanceGuard — STK-2026-07-27-CC-002.
 *
 * When the cadastral triple matches a prior committed application from
 * a different organization AND the owner name also matches, the manual
 * (via Abdullah's stakeholder input 2026-07-27) requires the applicant
 * to attach TWO documents from the previous engineering office before
 * the submission is accepted:
 *
 *   1. مخالصة              (previous_office_clearance) — settlement / no-dues certificate
 *   2. براءة ذمة رسمية      (previous_office_discharge) — official discharge letter
 *
 * Both are conditional documents on cadastral-scoped services (see
 * Srv001PilotSeeder for the current SRV-001 pilot). They are declared
 * with `required: false` in the schema so `SchemaValidator` doesn't
 * block a first-time submission that has no owner-match conflict —
 * this guard enforces the "required-when-owner-matches" condition.
 *
 * Runs AFTER CadastralConflictGuard in CrossCuttingSubmissionPipeline.
 * When CadastralConflictGuard already rejected on different-owner match,
 * the pipeline short-circuits before this guard runs; so this guard
 * only sees same-owner-or-no-conflict cases.
 *
 * Deferred (UNQ-019 in the addendum): on-platform issuance of the
 * clearance/discharge by the previous office. Current model = the
 * applicant obtains the PDFs off-platform and uploads them.
 */
final class OwnerMatchClearanceGuard implements CrossCuttingSubmissionGuard
{
    public const CLEARANCE_DOC_ID = 'previous_office_clearance';
    public const DISCHARGE_DOC_ID = 'previous_office_discharge';

    public function validate(Application $app): array
    {
        // Only fires on cadastral-scoped services with a complete triple.
        if (! CadastralPriorApplicationLookup::isInScope($app)) {
            return [];
        }
        $triple = CadastralPriorApplicationLookup::extractTriple($app);
        if ($triple === null) {
            return [];
        }

        $candidates = CadastralPriorApplicationLookup::candidates($app);
        if ($candidates->isEmpty()) {
            return []; // no conflict at all
        }

        $data = is_array($app->data) ? $app->data : [];
        $ownerName = $data['contract_owner_name'] ?? null;
        $split = CadastralPriorApplicationLookup::splitByOwner($candidates, $ownerName);

        // If any different-owner conflict exists, CadastralConflictGuard
        // will have already returned an error and this guard won't run.
        // Defensive check: if we're here and different-owner is set, do
        // NOT layer a clearance requirement on top of a hard rejection.
        if ($split['different_owner']->isNotEmpty()) {
            return [];
        }

        // Same-owner conflict only → require both clearance documents.
        if ($split['same_owner']->isEmpty()) {
            return []; // no conflict of either kind
        }

        $uploadedIds = $app->documents->pluck('document_id')->toArray();
        $missing = [];
        if (! in_array(self::CLEARANCE_DOC_ID, $uploadedIds, true)) {
            $missing[self::CLEARANCE_DOC_ID] = 'مطلوب إرفاق مخالصة من المكتب الهندسي السابق لأن نفس المالك سجَّل هذه القطعة معه سابقاً.';
        }
        if (! in_array(self::DISCHARGE_DOC_ID, $uploadedIds, true)) {
            $missing[self::DISCHARGE_DOC_ID] = 'مطلوب إرفاق براءة ذمة رسمية من المكتب الهندسي السابق لأن نفس المالك سجَّل هذه القطعة معه سابقاً.';
        }

        return $missing;
    }
}
