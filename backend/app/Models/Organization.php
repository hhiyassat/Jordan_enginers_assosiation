<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organization — platform tenant root.
 *
 * H-08: the previous version imported JEA models (`OfficeCoalition`,
 * `Application`, `ServiceDefinition`) and exposed `services()`,
 * `applications()`, `activeCoalition()` accessors that were either
 * unused or already deprecated. Those imports were removed so
 * Platform → JEA module coupling drops one link. Callers that need
 * per-tenant JEA rows query the module models directly
 * (`Application::forOrganization($org->id)` etc.).
 */
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

    /** @return HasMany<User, $this> */
    public function users(): HasMany { return $this->hasMany(User::class); }
}
