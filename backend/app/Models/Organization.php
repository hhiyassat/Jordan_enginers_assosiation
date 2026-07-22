<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\JeaProjects\Models\OfficeCoalition;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_ar', 'name_en', 'slug', 'logo_url', 'config', 'is_active',
        // JORD-70: ceiling-boost flags — default false so existing orgs
        // don't silently gain 5-15% extra quota after the migration.
        'has_excellence_award', 'is_bit_khibra', 'has_iso_cert',
    ];

    protected $casts = [
        'config'               => 'array',
        'is_active'            => 'boolean',
        'has_excellence_award' => 'boolean',
        'is_bit_khibra'        => 'boolean',
        'has_iso_cert'         => 'boolean',
    ];

    public function users(): HasMany        { return $this->hasMany(User::class); }
    public function services(): HasMany     { return $this->hasMany(ServiceDefinition::class); }
    public function applications(): HasMany { return $this->hasMany(Application::class); }

    /**
     * @deprecated JORD-77: coalitions moved to per-office (User).
     * Use User::activeCoalition() instead. Kept as a shim returning
     * null so any lingering call sites don't crash.
     */
    public function activeCoalition(): ?OfficeCoalition
    {
        return null;
    }
}
