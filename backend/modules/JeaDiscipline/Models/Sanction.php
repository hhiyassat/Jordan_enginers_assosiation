<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * JORD-81: one row per issued sanction. Outlives the parent
 * complaint (nullable FK) so an audit trail survives complaint
 * cleanup. SanctionGuard treats a sanction as "active" when
 * effective_from <= today AND (effective_until IS NULL OR
 * effective_until > today).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $office_user_id
 * @property int|null $complaint_id
 * @property string $kind
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string $reason
 * @property int $issued_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Sanction extends Model
{
    use SoftDeletes;

    public const KIND_WARNING = 'warning';

    public const KIND_SUSPENSION_1YR = 'suspension_1yr';

    public const KIND_SUSPENSION_2YR = 'suspension_2yr';

    public const KIND_DEREGISTRATION = 'deregistration';

    protected $fillable = [
        'organization_id', 'office_user_id', 'complaint_id',
        'kind', 'effective_from', 'effective_until', 'reason', 'issued_by_user_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_user_id');
    }

    /** @return BelongsTo<Complaint, $this> */
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
        if ($this->effective_from > $at) {
            return false;
        }
        if ($this->effective_until !== null && $this->effective_until < $at) {
            return false;
        }

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
