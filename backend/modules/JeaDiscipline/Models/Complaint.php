<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * JORD-81: one row per disciplinary complaint against an
 * engineering office. See migrations/2026_07_21_000013 for shape.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $target_office_user_id
 * @property int|null $reporter_user_id
 * @property string|null $reporter_display
 * @property string $kind
 * @property string $description
 * @property string $status
 * @property Carbon $investigation_deadline
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_user_id
 * @property string|null $decision_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Complaint extends Model
{
    use SoftDeletes;

    public const KIND_FEE_UNDERCUTTING = 'fee_undercutting';

    public const KIND_CONTRACTING_BAN = 'contracting_ban';

    public const KIND_SAFETY_VIOLATION = 'safety_violation';

    public const KIND_OTHER = 'other';

    public const STATUS_OPEN = 'open';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_DECIDED = 'decided';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id', 'target_office_user_id', 'reporter_user_id', 'reporter_display',
        'kind', 'description', 'status', 'investigation_deadline',
        'decided_at', 'decided_by_user_id', 'decision_notes',
    ];

    protected $casts = [
        'investigation_deadline' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function targetOffice(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_office_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return HasMany<Sanction, $this> */
    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }
}
