<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Global scope registered by BelongsToOrganization. Extracted to a
 * named class (not an anonymous class) so callers can remove it
 * with Model::withoutGlobalScope(OrganizationScope::class) — an
 * anonymous scope instance would be keyed by its runtime class name
 * and couldn't be matched deterministically.
 *
 * Behavior:
 *   - No authenticated user (console, seeders, integration jobs): no
 *     filter is applied. The trusted execution context is responsible
 *     for the scope of its work.
 *   - Auth user with a valid organization_id: filter to that org.
 *   - Auth user with a NULL organization_id: FAIL CLOSED — filter
 *     to an impossible id so no tenant rows are returned. This is
 *     the H-01 remediation; previously this case silently returned
 *     every tenant's rows unfiltered, which meant a misconfigured
 *     account (e.g. an integration user created outside the normal
 *     flow, or corrupted data) got god-mode read access.
 *
 * If you legitimately need cross-tenant access (admin cross-org
 * queries, integration jobs), use ::withoutOrgScope() explicitly.
 */
/** @implements Scope<Model> */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant Model> $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!Auth::check()) return;

        $orgId = Auth::user()->organization_id ?? null;

        if (!$orgId) {
            // Fail closed. Log once per request so ops sees the
            // symptom without spamming; then filter to an impossible
            // id so the query returns zero rows.
            Log::channel(config('logging.default'))->warning(
                'org_scope.null_org_authenticated_user',
                [
                    'user_id' => Auth::id(),
                    'model'   => $model::class,
                    'table'   => $model->getTable(),
                ],
            );
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.organization_id', $orgId);
    }
}
