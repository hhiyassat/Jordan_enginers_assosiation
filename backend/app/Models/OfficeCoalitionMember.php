<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JORD-73: pivot row on office_coalition_members. Carries joined_at /
 * left_at so a coalition can track member churn during its lifetime.
 */
class OfficeCoalitionMember extends Model
{
    protected $fillable = ['office_coalition_id', 'organization_id', 'joined_at', 'left_at'];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
    ];

    public function coalition(): BelongsTo
    {
        return $this->belongsTo(OfficeCoalition::class, 'office_coalition_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
