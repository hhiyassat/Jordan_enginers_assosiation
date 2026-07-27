<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\JeaServices\Models\Application;

/**
 * JORD-68: one row per (approved application × engineer × discipline).
 * See migrations/2026_07_21_000003_create_quota_consumptions_table.php
 * and Modules\JeaProjects\Engine\QuotaLedger for the write path.
 *
 * @property int $id
 * @property int $application_id
 * @property int $engineer_id
 * @property int $organization_id
 * @property string $discipline
 * @property int $year
 * @property int $m2
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $governorate
 * @property int|null $office_user_id
 */
class QuotaConsumption extends Model
{
    protected $fillable = [
        'application_id', 'engineer_id', 'organization_id', 'office_user_id',
        'discipline', 'governorate', 'year', 'm2',
    ];

    protected $casts = [
        'year' => 'integer',
        'm2' => 'integer',
    ];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Engineer, $this> */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(Engineer::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
