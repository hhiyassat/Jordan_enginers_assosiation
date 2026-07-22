<?php

declare(strict_types=1);

namespace Modules\JeaDues\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JORD-79: one row per (office × kind × period_year).
 *
 * Workstream 7: moved from App\Models\RecurringObligation into the
 * jea-dues module. Class body is verbatim; only the namespace and
 * the (now cross-boundary) User/Organization imports changed.
 *
 * See Modules\JeaDues\Database\Migrations\... for the schema and
 * Modules\JeaDues\Services\RecurringDuesService for the write path.
 */
class RecurringObligation extends Model
{
    use SoftDeletes;

    public const KIND_REGISTRATION = 'registration';
    public const KIND_ANNUAL_DUES  = 'annual_dues';

    protected $fillable = [
        'organization_id', 'office_user_id',
        'kind', 'period_year', 'period_label_ar',
        'amount_jod', 'due_date',
        'paid_at', 'payment_reference',
        'late_surcharge_jod', 'total_paid_jod',
    ];

    protected $casts = [
        'period_year'         => 'integer',
        'amount_jod'          => 'decimal:2',
        'due_date'            => 'date',
        'paid_at'             => 'datetime',
        'late_surcharge_jod'  => 'decimal:2',
        'total_paid_jod'      => 'decimal:2',
    ];

    public function officeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
