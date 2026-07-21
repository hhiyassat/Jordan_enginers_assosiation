<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JORD-67: per-organization (office) per-discipline yearly m² ceiling.
 *
 * See migrations/2026_07_21_000002_create_office_ceilings_table.php.
 */
class OfficeCeiling extends Model
{
    protected $fillable = [
        'organization_id', 'discipline', 'year', 'm2_allowed',
        // JORD-72: per-single-project cap per JEA p.129. Null = no cap.
        'per_project_cap_m2',
    ];

    protected $casts = [
        'year'               => 'integer',
        'm2_allowed'         => 'integer',
        'per_project_cap_m2' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
