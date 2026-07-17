<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'owner_user_id', 'name_ar', 'name_en',
        'type', 'area_m2', 'city', 'contract_no', 'request_no', 'status',
    ];

    protected $casts = [
        'area_m2' => 'integer',
    ];

    // organization() provided by BelongsToOrganization trait

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
