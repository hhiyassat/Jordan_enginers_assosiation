<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * JORD-67: per-organization (office) per-discipline yearly m² ceiling.
 *
 * See migrations/2026_07_21_000002_create_office_ceilings_table.php.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $discipline
 * @property int $year
 * @property int $m2_allowed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $per_project_cap_m2
 * @property int|null $office_user_id
 */
class OfficeCeiling extends Model
{
    protected $fillable = [
        'organization_id', 'office_user_id', 'discipline', 'year', 'm2_allowed',
        // JORD-72: per-single-project cap per JEA p.129. Null = no cap.
        'per_project_cap_m2',
    ];

    protected $casts = [
        'year' => 'integer',
        'm2_allowed' => 'integer',
        'per_project_cap_m2' => 'integer',
    ];

    /** JORD-77: primary FK is office_user_id (per-office). */
    /** @return BelongsTo<User, $this> */
    public function officeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
