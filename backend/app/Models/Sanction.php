<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JORD-81: one row per issued sanction. Outlives the parent
 * complaint (nullable FK) so an audit trail survives complaint
 * cleanup. SanctionGuard treats a sanction as "active" when
 * effective_from <= today AND (effective_until IS NULL OR
 * effective_until > today).
 */
class Sanction extends Model
{
    use SoftDeletes;

    public const KIND_WARNING          = 'warning';
    public const KIND_SUSPENSION_1YR   = 'suspension_1yr';
    public const KIND_SUSPENSION_2YR   = 'suspension_2yr';
    public const KIND_DEREGISTRATION   = 'deregistration';

    protected $fillable = [
        'organization_id', 'office_user_id', 'complaint_id',
        'kind', 'effective_from', 'effective_until', 'reason', 'issued_by_user_id',
    ];

    protected $casts = [
        'effective_from'  => 'date',
        'effective_until' => 'date',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_user_id');
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * "Active" means the sanction is currently in force — a warning
     * never blocks submissions (informational only), a suspension
     * with future or past effective window doesn't either.
     */
    public function isActive(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? now();
        if ($this->effective_from > $at) return false;
        if ($this->effective_until !== null && $this->effective_until < $at) return false;
        return true;
    }

    public function isBlocking(): bool
    {
        // Warnings never block — they're advisory. Suspensions +
        // deregistration do.
        return in_array($this->kind, [
            self::KIND_SUSPENSION_1YR,
            self::KIND_SUSPENSION_2YR,
            self::KIND_DEREGISTRATION,
        ], true);
    }
}
