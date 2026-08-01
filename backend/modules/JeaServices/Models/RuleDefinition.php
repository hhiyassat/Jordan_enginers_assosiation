<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SG-04 · registered business rule / calculator.
 *
 * @property int         $id
 * @property string      $rule_identifier
 * @property string      $display_name
 * @property string|null $description
 */
class RuleDefinition extends Model
{
    protected $fillable = ['rule_identifier', 'display_name', 'description'];

    /** @return HasMany<RuleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RuleVersion::class);
    }

    public function currentApprovedVersion(): ?RuleVersion
    {
        return $this->versions()
            ->where('business_approval_status', 'APPROVED')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Returns the most recent version regardless of approval status.
     * Used when a legacy PROVISIONAL calculator is the only rule available.
     */
    public function currentEffectiveVersion(): ?RuleVersion
    {
        return $this->versions()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
