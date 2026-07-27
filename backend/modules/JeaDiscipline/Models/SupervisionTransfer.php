<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\JeaServices\Models\Application;

/**
 * JORD-83: one row per application whose supervising office was
 * dissolved and needs a new office to take over. See migration
 * 2026_07_21_000015 for shape.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_id
 * @property int $source_office_user_id
 * @property int|null $target_office_user_id
 * @property int|null $triggering_sanction_id
 * @property string $status
 * @property bool $fee_waived
 * @property string|null $notes
 * @property Carbon|null $assigned_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SupervisionTransfer extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'organization_id', 'application_id',
        'source_office_user_id', 'target_office_user_id',
        'triggering_sanction_id',
        'status', 'fee_waived', 'notes',
        'assigned_at', 'accepted_at',
    ];

    protected $casts = [
        'fee_waived' => 'boolean',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sourceOffice(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_office_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function targetOffice(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_office_user_id');
    }

    /** @return BelongsTo<Sanction, $this> */
    public function triggeringSanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class, 'triggering_sanction_id');
    }
}
