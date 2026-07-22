<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JORD-68: one row per (approved application × engineer × discipline).
 * See migrations/2026_07_21_000003_create_quota_consumptions_table.php
 * and Modules\JeaProjects\Engine\QuotaLedger for the write path.
 */
class QuotaConsumption extends Model
{
    protected $fillable = [
        'application_id', 'engineer_id', 'organization_id', 'office_user_id',
        'discipline', 'governorate', 'year', 'm2',
    ];

    protected $casts = [
        'year' => 'integer',
        'm2'   => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(Engineer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
