<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * CadastralConflictGuard — STK-2026-07-27-CC-001.
 *
 * Rejects a submission when the cadastral triple matches a prior
 * committed application from a DIFFERENT organization AND the owner
 * name is different. Different-owner match is the pure conflict case
 * (two offices trying to file for two different owners on the same
 * parcel) that must be blocked immediately.
 *
 * Same-owner match is a DIFFERENT case — it means the same owner has
 * been engaging different offices, which the manual allows subject to
 * a clearance + discharge from the previous office. That flow is
 * enforced by OwnerMatchClearanceGuard (CC-002); this guard passes
 * through when owner matches so CC-002 can raise the specific
 * clearance requirement.
 *
 * Pipeline ordering (JeaServicesServiceProvider):
 *   1. CadastralConflictGuard         (this) — rejects on different-owner conflict
 *   2. OwnerMatchClearanceGuard       — requires clearance/discharge on same-owner conflict
 *
 * Behavior boundaries (unchanged from the initial CC-001 commit):
 *   • Only fires when the service declares all three cadastral fields.
 *   • Same-organization prior applications never block same-org submissions.
 *   • Draft + rejected statuses are not considered "committed".
 *   • Arabic text matching via ArabicNormalizer.
 *   • Uses Application::withoutOrgScope() (see CadastralPriorApplicationLookup)
 *     to bypass the BelongsToOrganization global scope that would otherwise
 *     hide cross-org candidates under Sanctum context.
 */
final class CadastralConflictGuard implements CrossCuttingSubmissionGuard
{
    public function validate(Application $app): array
    {
        if (! CadastralPriorApplicationLookup::isInScope($app)) {
            return [];
        }

        $triple = CadastralPriorApplicationLookup::extractTriple($app);
        if ($triple === null) {
            return []; // SchemaValidator will have already reported the missing field
        }

        $candidates = CadastralPriorApplicationLookup::candidates($app);
        if ($candidates->isEmpty()) {
            return [];
        }

        $data = is_array($app->data) ? $app->data : [];
        $split = CadastralPriorApplicationLookup::splitByOwner(
            $candidates,
            $data['contract_owner_name'] ?? null,
        );

        if ($split['different_owner']->isNotEmpty()) {
            return [
                'basin_number' => 'هذه القطعة والحوض مسجَّلان سابقاً لمكتب هندسي آخر بمالك مختلف. لا يمكن التقديم قبل تسوية الوضع مع المكتب السابق أو النقابة.',
            ];
        }

        // Only same-owner conflicts remain → OwnerMatchClearanceGuard will
        // handle the clearance requirement. This guard passes.
        return [];
    }
}
