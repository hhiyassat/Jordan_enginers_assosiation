<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JORD-67: per-engineer per-discipline yearly m² quota row.
 *
 * See migrations/2026_07_21_000001_create_engineer_discipline_quotas_table.php
 * for the schema shape. Reads are always keyed on
 * (engineer_id, discipline, year) — the composite unique enforces
 * that at the DB level, so a Model::where(...)->first() is safe.
 */
class EngineerDisciplineQuota extends Model
{
    protected $fillable = ['engineer_id', 'discipline', 'year', 'm2_allowed'];

    protected $casts = [
        'year'        => 'integer',
        'm2_allowed'  => 'integer',
    ];

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(Engineer::class);
    }
}
