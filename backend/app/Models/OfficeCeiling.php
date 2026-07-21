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
    protected $fillable = ['organization_id', 'discipline', 'year', 'm2_allowed'];

    protected $casts = [
        'year'        => 'integer',
        'm2_allowed'  => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
