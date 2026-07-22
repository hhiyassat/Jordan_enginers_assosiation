<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Models;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JORD-83: one row per application whose supervising office was
 * dissolved and needs a new office to take over. See migration
 * 2026_07_21_000015 for shape.
 */
class SupervisionTransfer extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING  = 'pending';
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
        'fee_waived'   => 'boolean',
        'assigned_at'  => 'datetime',
        'accepted_at'  => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function sourceOffice(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_office_user_id');
    }

    public function targetOffice(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_office_user_id');
    }

    public function triggeringSanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class, 'triggering_sanction_id');
    }
}
