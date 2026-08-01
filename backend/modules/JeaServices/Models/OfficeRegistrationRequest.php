<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * OfficeRegistrationRequest — public office-registration submission.
 *
 * See docs/architecture/office-registration-flow.md.
 *
 * Deliberately NOT tenant-scoped (BelongsToOrganization NOT used) — these
 * rows are pre-organizational and any JEA staff/admin can review any of
 * them regardless of their own organization membership.
 */
class OfficeRegistrationRequest extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'reference_number',
        'office_name_ar', 'office_name_en', 'office_license_no',
        'office_city', 'office_address_ar', 'office_phone', 'office_email',
        'engineers',
        'status', 'review_notes', 'reviewed_by_user_id', 'reviewed_at',
        'approved_organization_id',
        'submitter_ip', 'submitter_user_agent',
    ];

    protected $casts = [
        'engineers'   => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function approvedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'approved_organization_id');
    }

    /**
     * Generate a stable reference number for a fresh submission.
     *
     * Format: OFR-{YY}-{6-char random}
     *   e.g. OFR-26-A3F92C
     * Unique-checked by caller (retry on collision unlikely at 16M-space).
     */
    public static function generateReference(): string
    {
        return sprintf('OFR-%s-%s', date('y'), strtoupper(Str::random(6)));
    }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
}
