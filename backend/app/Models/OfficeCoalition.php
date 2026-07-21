<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * JORD-73: a coalition of engineering offices pooling their quotas.
 * See migrations/2026_07_21_000008 and _000009 for shape, and
 * App\Engine\QuotaLedger::remainingOfficeCeiling for the aggregated
 * ceiling math.
 */
class OfficeCoalition extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'formed_at', 'dissolved_at'];

    protected $casts = [
        'formed_at'    => 'datetime',
        'dissolved_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(OfficeCoalitionMember::class);
    }

    /** Members with an active membership (not yet left). */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    public function isActive(): bool
    {
        return $this->dissolved_at === null;
    }
}
