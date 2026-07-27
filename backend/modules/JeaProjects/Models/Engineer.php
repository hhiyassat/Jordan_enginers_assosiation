<?php

namespace Modules\JeaProjects\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Engineer
 *
 * A registered engineer working under an engineering office (applicant
 * user). Each engineer has an annual m² quota that ProjectController
 * enforces when the office attributes a new project to them.
 *
 * NFR-002: BelongsToOrganization → scoped by tenant.
 * DATA-004: soft-deletes for audit trail.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $office_user_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property string $membership_number
 * @property string|null $specialization
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $annual_quota_m2
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_specialization_head
 */
class Engineer extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'office_user_id',
        'name_ar', 'name_en',
        'membership_number', 'specialization',
        'phone', 'email',
        'annual_quota_m2', 'is_active',
        // JORD-70: +20% quota boost when this engineer heads the office's
        // specialization for their discipline. Default false.
        'is_specialization_head',
    ];

    protected $casts = [
        'annual_quota_m2' => 'integer',
        'is_active' => 'boolean',
        'is_specialization_head' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(User::class, 'office_user_id');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
