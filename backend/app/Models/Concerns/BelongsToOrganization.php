<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
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
 * The global scope silently no-ops when:
 *   - There is no authenticated user (console, seeders, integration jobs)
 *   - The authenticated user has no organization_id (superuser / integration)
 * This keeps existing seeders and cross-tenant admin queries working without
 * forcing every caller to opt out.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new class implements Scope {
            public function apply(Builder $builder, $model): void
            {
                if (!Auth::check()) return;
                $orgId = Auth::user()->organization_id ?? null;
                if (!$orgId) return;

                $builder->where($model->getTable() . '.organization_id', $orgId);
            }
        });

        // On create, backfill organization_id from the auth user if unset.
        static::creating(function ($model) {
            if (!$model->organization_id && Auth::check()) {
                $orgId = Auth::user()->organization_id ?? null;
                if ($orgId) $model->organization_id = $orgId;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where($this->getTable() . '.organization_id', $orgId);
    }

    public function scopeForCurrentOrganization(Builder $query): Builder
    {
        $orgId = Auth::check() ? (Auth::user()->organization_id ?? null) : null;
        return $orgId ? $this->scopeForOrganization($query, $orgId) : $query;
    }

    /**
     * Escape the global org scope. Use ONLY for admin cross-org queries,
     * integration jobs, or console context where scoping doesn't apply.
     */
    public static function withoutOrgScope(): Builder
    {
        return static::query()->withoutGlobalScope(self::class);
    }
}
