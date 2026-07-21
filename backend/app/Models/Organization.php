<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
     * JORD-73: the coalition this office currently belongs to, if any.
     * A membership is "active" iff both:
     *   • the coalition itself hasn't been dissolved
     *   • the office hasn't left it
     * Returns null when the office is standalone (the common case).
     */
    public function activeCoalition(): ?OfficeCoalition
    {
        $member = OfficeCoalitionMember::where('organization_id', $this->id)
            ->whereNull('left_at')
            ->latest()
            ->first();
        if (!$member) return null;
        $coalition = $member->coalition;
        return ($coalition && $coalition->isActive()) ? $coalition : null;
    }
}
