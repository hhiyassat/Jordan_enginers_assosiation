<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * JORD-73: pivot row on office_coalition_members. Carries joined_at /
 * left_at so a coalition can track member churn during its lifetime.
 *
 * @property int $id
 * @property int $office_coalition_id
 * @property int $organization_id
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $office_user_id
 */
class OfficeCoalitionMember extends Model
{
    protected $fillable = ['office_coalition_id', 'organization_id', 'office_user_id', 'joined_at', 'left_at'];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /** @return BelongsTo<OfficeCoalition, $this> */
    public function coalition(): BelongsTo
    {
        return $this->belongsTo(OfficeCoalition::class, 'office_coalition_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
