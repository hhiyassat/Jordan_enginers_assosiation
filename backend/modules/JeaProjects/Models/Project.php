<?php

namespace Modules\JeaProjects\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $owner_user_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $type
 * @property int|null $area_m2
 * @property string|null $city
 * @property string|null $contract_no
 * @property string|null $request_no
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $engineer_id
 */
class Project extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'owner_user_id', 'engineer_id',
        'name_ar', 'name_en',
        'type', 'area_m2', 'city', 'contract_no', 'request_no', 'status',
    ];

    protected $casts = [
        'area_m2' => 'integer',
    ];

    // organization() provided by BelongsToOrganization trait

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<Engineer, $this> */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(Engineer::class);
    }
}
