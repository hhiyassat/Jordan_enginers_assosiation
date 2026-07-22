<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope registered by BelongsToOrganization. Extracted to a
 * named class (not an anonymous class) so callers can remove it
 * with Model::withoutGlobalScope(OrganizationScope::class) — an
 * anonymous scope instance would be keyed by its runtime class name
 * and couldn't be matched deterministically.
 *
 * Skips filtering when:
 *   - No authenticated user (seeders, integration jobs, console)
 *   - Auth user has no organization_id (superuser / integration)
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
        if (!$orgId) return;

        $builder->where($model->getTable() . '.organization_id', $orgId);
    }
}
