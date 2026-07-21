<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JORD-82: legal fine issued against a project owner per JEA
 * manual Art.14 (p. 251). See migration for shape.
 *
 * Bounds by kind (JOD):
 *   unlicensed_contractor_small (≤250 m²): 1,000 – 5,000
 *   unlicensed_contractor_large (>250 m²): 5,000 – 50,000
 *
 * Bounds live on the model (not the DB) so the controller can
 * validate range without a schema change if the manual is amended.
 */
class LegalFine extends Model
{
    use SoftDeletes;

    public const KIND_UNLICENSED_SMALL = 'unlicensed_contractor_small';
    public const KIND_UNLICENSED_LARGE = 'unlicensed_contractor_large';

    /** @var array<string, array{min: int, max: int, area_threshold_m2: int|null}> */
    public const BOUNDS = [
        self::KIND_UNLICENSED_SMALL => [
            'min'                => 1000,
            'max'                => 5000,
            'area_threshold_m2'  => 250,   // small kind is valid iff area ≤ this
        ],
        self::KIND_UNLICENSED_LARGE => [
            'min'                => 5000,
            'max'                => 50000,
            'area_threshold_m2'  => 250,   // large kind is valid iff area > this
        ],
    ];

    protected $fillable = [
        'organization_id', 'application_id', 'target_display',
        'kind', 'project_area_m2', 'amount_jod', 'reason',
        'issued_by_user_id', 'issued_at', 'paid_at', 'payment_reference',
    ];

    protected $casts = [
        'project_area_m2' => 'integer',
        'amount_jod'      => 'decimal:2',
        'issued_at'       => 'datetime',
        'paid_at'         => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
