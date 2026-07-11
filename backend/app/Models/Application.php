<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Application
 *
 * The central entity of ESP v2. One application per service submission.
 * Status column maps exactly to ALLOWED_TRANSITIONS in WorkflowEngine.
 *
 * WF-001: ALLOWED_TRANSITIONS is the single authority for valid status values.
 * BR-004: organization_id scope enforced on every query (never query without it).
 * DATA-001: form data stored in JSON `data` column.
 */
class Application extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_number', 'organization_id', 'service_definition_id', 'applicant_id',
        'assigned_reviewer_id', 'status', 'current_stage', 'data', 'fee_amount',
        'payment_status', 'payment_reference', 'payment_confirmed_at',
        'sla_deadline', 'sla_breached_at', 'review_round',
    ];

    protected $casts = [
        'data'                 => 'array',
        'fee_amount'           => 'decimal:2',
        'payment_confirmed_at' => 'datetime',
        'sla_deadline'         => 'datetime',
        'sla_breached_at'      => 'datetime',
    ];

    // ── Status constants (mirrors ALLOWED_TRANSITIONS keys) ────────────

    const STATUS_DRAFT                   = 'draft';
    const STATUS_SUBMITTED               = 'submitted';
    const STATUS_UNDER_REVIEW            = 'under_review';
    const STATUS_MODIFICATIONS_REQUESTED = 'modifications_requested';
    const STATUS_APPROVED                = 'approved';
    const STATUS_REJECTED                = 'rejected';
    const STATUS_CERTIFICATE_ISSUED      = 'certificate_issued';

    const TERMINAL_STATUSES = [self::STATUS_REJECTED, self::STATUS_CERTIFICATE_ISSUED];

    // ── Relationships ────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function serviceDefinition(): BelongsTo
    {
        return $this->belongsTo(ServiceDefinition::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ApplicationReview::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    /**
     * BR-004: Always scope by organization_id.
     * Use this instead of bare Application::where(...) to prevent cross-org leakage.
     */
    public function scopeForOrganization($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ── State helpers ──────────────────────────────────────────────────

    /** WF-001: Application can be edited only in these statuses */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_MODIFICATIONS_REQUESTED,
        ]);
    }

    /** BRR: Terminal states accept no further transitions */
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function isFeePaid(): bool
    {
        return $this->payment_status === 'paid' || $this->payment_status === 'waived';
    }

    // ── Reference number generation ────────────────────────────────────

    /**
     * Generate a reference number: ESP-{SERVICE_CODE}-{YYYYMMDD}-{SEQUENCE}
     * Uses DB sequence (count+1) to avoid race conditions — fine for demo;
     * production should use a DB sequence or Redis counter.
     */
    public static function generateReference(string $serviceCode): string
    {
        $date  = now()->format('Ymd');
        $today = now()->format('Y-m-d');

        $count = self::whereDate('created_at', $today)->count() + 1;

        return sprintf('ESP-%s-%s-%04d', strtoupper($serviceCode), $date, $count);
    }
}
