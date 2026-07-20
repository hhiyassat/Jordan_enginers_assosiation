<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Application
 *
 * The central entity of ESP v2. One application per service submission.
 * Status column maps exactly to ALLOWED_TRANSITIONS in WorkflowEngine.
 *
 * WF-001: ALLOWED_TRANSITIONS is the single authority for valid status values.
 * BR-004: organization_id scope enforced on every query (never query without it).
 * DATA-001: form data stored in JSON `data` column.
 *
 * @property int                                $id
 * @property string                             $reference_number
 * @property string                             $status
 * @property string|null                        $current_stage
 * @property int                                $applicant_id
 * @property int|null                           $assigned_reviewer_id
 * @property array<string, mixed>|null          $data
 * @property float|string                       $fee_amount
 * @property ServiceDefinition|null             $serviceDefinition
 * @property Project|null                       $project
 * @property Certificate|null                   $certificate
 * @property User|null                          $applicant
 */
class Application extends Model
{
    use SoftDeletes, BelongsToOrganization;

    protected $fillable = [
        'reference_number', 'contract_no', 'organization_id', 'service_definition_id', 'project_id', 'applicant_id',
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
    // organization() provided by BelongsToOrganization trait

    public function serviceDefinition(): BelongsTo
    {
        return $this->belongsTo(ServiceDefinition::class);
    }

    /**
     * Optional link to the applicant's project — populated when the Apply
     * flow was reached from /projects/{id}/…. Nullable because certificates,
     * financial requests, and other non-drawing services carry no project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
    // scopeForOrganization + global scope provided by BelongsToOrganization trait

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

    /**
     * JORD-59: supervision-contract expiry per JEA 2025 manual p. 27.
     *   "يكون عقد الاشراف ملزماً ... ستة اشهر من تاريخ اجازة المخططات"
     *
     * Returns the date by which supervision work must have started, or
     * null when the rule doesn't apply. The rule applies iff:
     *   • The service is under JEA-PROJ (drawings approval).
     *   • The service's schema declares the supervision_services_agreement
     *     document (which the JORD-54 shared-docs manifest gives every
     *     DRW-P-*). Services that offload supervision to a separate
     *     contract don't get an auto-expiry here.
     *   • The application has at least one review with decision='approved' —
     *     otherwise there's no clock to start.
     *
     * The anchor date is the LATEST approved-decision review, not
     * status transitions or updated_at. Reasoning:
     *   • Multi-stage flows can have several 'approved' reviews (one
     *     per stage). The latest is the one that pushed the app into
     *     STATUS_APPROVED / STATUS_CERTIFICATE_ISSUED. Anchoring off
     *     status columns is fragile because status can move to
     *     certificate_issued and updated_at shifts.
     *   • ApplicationReview is append-only, so once written the
     *     timestamp is stable for the retention window.
     *
     * The window is config-driven (SUPERVISION_WINDOW_MONTHS, default 6)
     * so a policy change reaches production via .env without a code deploy.
     */
    public function getSupervisionExpiryAttribute(): ?Carbon
    {
        $svc = $this->serviceDefinition;
        if (!$svc || $svc->parent_code !== 'JEA-PROJ') {
            return null;
        }

        $documents = data_get($svc->schema, 'documents', []);
        $hasSupervisionDoc = collect($documents)
            ->contains(fn ($d) => ($d['id'] ?? null) === 'supervision_services_agreement');
        if (!$hasSupervisionDoc) {
            return null;
        }

        $approvedAt = $this->reviews()
            ->where('decision', Application::STATUS_APPROVED)
            ->latest('created_at')
            ->value('created_at');
        if (!$approvedAt) {
            return null;
        }

        $windowMonths = (int) config('esp.supervision_window_months', 6);
        return Carbon::parse($approvedAt)->addMonths($windowMonths);
    }

    // ── Reference number generation ────────────────────────────────────

    /**
     * NFR-008: Generate a 10-digit reference number of the form
     *   {YY}{ServiceCode:4}{Seq:4}
     * e.g. 2620010001 for service_definition_id 2001, first submission of 2026.
     *
     * The ServiceCode segment is derived from ServiceDefinition::id padded to
     * 4 digits — stable across renames, unique per tenant. Sequence is
     * per-service-per-year to give 9999 slots without collision.
     *
     * Old alpha references (ESP-XXX-...) remain valid for existing rows;
     * only new records get the numeric format.
     */
    public static function generateReference(ServiceDefinition $service): string
    {
        $yy      = str_pad((string) (now()->year % 100), 2, '0', STR_PAD_LEFT);
        $svcCode = str_pad((string) ($service->id % 10000), 4, '0', STR_PAD_LEFT);

        $yearStart = now()->startOfYear();
        $seq = self::withoutOrgScope()
            ->where('service_definition_id', $service->id)
            ->where('created_at', '>=', $yearStart)
            ->count() + 1;

        $seqStr = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        return $yy . $svcCode . $seqStr;
    }
}
