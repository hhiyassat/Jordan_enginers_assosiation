<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * BelongsToOrganization
 *
 * NFR-002: Every tenant-scoped model uses this trait so isolation is
 * inherited automatically — new models added later just add `use
 * BelongsToOrganization` and the multi-tenancy guarantee comes with them.
 *
 * Provides:
 *   • organization() relation
 *   • ->forOrganization($orgId) scope
 *   • ->forCurrentOrganization() scope (reads Auth::user()->organization_id)
 *   • Global scope: when a request has an authenticated user with an
 *     organization_id, queries auto-filter to that org unless
 *     ::withoutOrgScope() is used.
 *
 * The global scope behaves as follows:
 *   - No authenticated user (console, seeders, integration jobs): no filter.
 *     Trusted execution context.
 *   - Authenticated user with a valid organization_id: filters to that org.
 *   - Authenticated user with a NULL organization_id: FAILS CLOSED — returns
 *     zero rows and emits a security warning. See OrganizationScope for
 *     the H-01 remediation details.
 *
 * For legitimate cross-tenant reads (admin dashboards, integration jobs),
 * call ::withoutOrgScope() explicitly at the call site.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        // Global scope is a named class (not anonymous) so ::withoutOrgScope()
        // can remove it by name.
        static::addGlobalScope(new OrganizationScope());

        // On create, backfill organization_id from the auth user if unset.
        static::creating(function ($model) {
            if (!$model->organization_id && Auth::check()) {
                $orgId = Auth::user()->organization_id ?? null;
                if ($orgId) $model->organization_id = $orgId;
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where($this->getTable() . '.organization_id', $orgId);
    }

    /**
     * @param  Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeForCurrentOrganization(Builder $query): Builder
    {
        if (!Auth::check()) {
            // Trusted execution context (console, seeder). Caller is
            // responsible for scoping.
            return $query;
        }
        $orgId = Auth::user()->organization_id ?? null;
        if (!$orgId) {
            // H-01: authenticated user with no organization_id is a
            // misconfiguration. Fail closed — return zero rows.
            return $query->whereRaw('1 = 0');
        }
        return $this->scopeForOrganization($query, $orgId);
    }

    /**
     * Escape the global org scope. Use ONLY for admin cross-org queries,
     * integration jobs, or console context where scoping doesn't apply.
     *
     * @return Builder<static>
     */
    public static function withoutOrgScope(): Builder
    {
        return static::query()->withoutGlobalScope(OrganizationScope::class);
    }
}
