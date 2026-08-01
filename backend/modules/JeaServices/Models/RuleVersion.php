<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SG-04 · a specific version of a business rule / calculator.
 *
 * @property int                              $id
 * @property int                              $rule_definition_id
 * @property string                           $version_identifier
 * @property string                           $implementation_identity
 * @property string                           $source_reference
 * @property string                           $business_approval_status
 * @property \Illuminate\Support\Carbon|null  $effective_from
 * @property \Illuminate\Support\Carbon|null  $effective_to
 */
class RuleVersion extends Model
{
    public const STATUS_APPROVED    = 'APPROVED';
    public const STATUS_PROVISIONAL = 'PROVISIONAL';
    public const STATUS_REJECTED    = 'REJECTED';
    public const STATUS_PENDING     = 'PENDING';

    protected $fillable = [
        'rule_definition_id', 'version_identifier',
        'implementation_identity', 'source_reference',
        'business_approval_status',
        'effective_from', 'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to'   => 'datetime',
    ];

    /** @return BelongsTo<RuleDefinition, $this> */
    public function ruleDefinition(): BelongsTo
    {
        return $this->belongsTo(RuleDefinition::class);
    }

    public function isApproved(): bool
    {
        return $this->business_approval_status === self::STATUS_APPROVED;
    }
}
