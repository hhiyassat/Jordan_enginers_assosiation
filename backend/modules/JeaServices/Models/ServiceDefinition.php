<?php

namespace Modules\JeaServices\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\JeaServices\Governance\ServiceLifecycleState;

/**
 * ServiceDefinition
 *
 * BR-001: schema JSON column is the source of truth for service configuration.
 * SG-00: complete runtime behaviour = schema + engine + guards + extensions + external state.
 * BR-005: workflow stages are read from schema, never hardcoded.
 *
 * @property int                                   $id
 * @property int                                   $organization_id
 * @property string                                $code
 * @property string|null                           $parent_code
 * @property string                                $name_ar
 * @property string                                $name_en
 * @property array<string, mixed>                  $schema
 * @property string                                $status
 * @property int|null                              $phase
 * @property bool                                  $is_locked
 * @property string                                $uat_status
 * @property string|null                           $uat_reference
 * @property \Illuminate\Support\Carbon|null       $uat_signed_at
 * @property int|null                              $uat_signed_by
 * @property string                                $publication_status
 * @property \Illuminate\Support\Carbon|null       $published_at
 * @property int|null                              $published_by
 * @property \Illuminate\Support\Carbon|null       $effective_from
 * @property \Illuminate\Support\Carbon|null       $suspended_at
 * @property int|null                              $suspended_by
 * @property string|null                           $suspension_reason
 * @property \Illuminate\Support\Carbon|null       $retired_at
 * @property int|null                              $retired_by
 * @property string|null                           $retirement_reason
 * @property string|null                           $publication_reason
 */
class ServiceDefinition extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'code', 'parent_code',
        'subcategory_ar', 'subcategory_en',
        'name_ar', 'name_en',
        'description_ar', 'description_en', 'currency', 'base_fee', 'sla_hours',
        'schema', 'status', 'phase', 'is_locked',
        // SG-01 governance columns
        'uat_status', 'uat_reference', 'uat_signed_at', 'uat_signed_by',
        'publication_status', 'published_at', 'published_by', 'effective_from',
        'suspended_at', 'suspended_by', 'suspension_reason',
        'retired_at', 'retired_by', 'retirement_reason',
        'publication_reason',
    ];

    protected $casts = [
        'schema'         => 'array',
        'base_fee'       => 'decimal:2',
        'sla_hours'      => 'integer',
        'phase'          => 'integer',
        'is_locked'      => 'boolean',
        // SG-01 governance timestamps
        'uat_signed_at'  => 'datetime',
        'published_at'   => 'datetime',
        'effective_from' => 'datetime',
        'suspended_at'   => 'datetime',
        'retired_at'     => 'datetime',
    ];

    /**
     * A locked service refuses every API-layer mutation (update, status
     * toggle, chat-schema). Only an admin or superuser may unlock it, and
     * the intended flow is: unlock → make the edit → re-lock. Seeders
     * bypass this because they hit Eloquent directly, not the API.
     */
    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    // ── Relationships ─────────────────────────────────────────────────
    // organization() provided by BelongsToOrganization trait

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    // ── Schema accessors (typed for engine use) ────────────────────────

    /** @return list<array<string, mixed>> */
    public function getWorkflowStages(): array
    {
        return $this->schema['workflow']['stages'] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function getStage(string $stageId): ?array
    {
        foreach ($this->getWorkflowStages() as $stage) {
            if ($stage['id'] === $stageId) {
                return $stage;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public function getFirstStage(): ?array
    {
        return $this->getWorkflowStages()[0] ?? null;
    }

    /**
     * First stage that isn't owned by the applicant — the workflow position
     * an application should occupy AFTER submit. Applicant-owned stages
     * (typically 'office_submission') are the draft-authoring phase; once
     * the applicant clicks submit, the case moves to the first reviewer
     * stage so a staff/auditor can claim it. Falls back to getFirstStage()
     * if the entire workflow is applicant-owned (unusual but valid).
     *
     * @return array<string, mixed>|null
     */
    public function getFirstReviewerStage(): ?array
    {
        foreach ($this->getWorkflowStages() as $stage) {
            if (($stage['role'] ?? null) !== 'applicant') {
                return $stage;
            }
        }
        return $this->getFirstStage();
    }

    /** @return list<array<string, mixed>> */
    public function getFields(): array
    {
        return $this->schema['fields'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function getDocuments(): array
    {
        return $this->schema['documents'] ?? [];
    }

    /** @return array<string, mixed> */
    public function getFeeConfig(): array
    {
        return $this->schema['fee'] ?? [];
    }

    /** @return array<string, mixed> */
    public function getCertificateConfig(): array
    {
        return $this->schema['certificate'] ?? [];
    }

    // ── SG-01 governance derivations ──────────────────────────────────

    public function hasUatApproval(): bool
    {
        return $this->uat_status === 'APPROVED'
            && !empty($this->uat_reference)
            && $this->uat_signed_at !== null;
    }

    public function isPublished(): bool
    {
        return $this->publication_status === 'PUBLISHED';
    }

    public function isSuspended(): bool
    {
        return $this->publication_status === 'SUSPENDED';
    }

    public function isRetired(): bool
    {
        return $this->publication_status === 'RETIRED';
    }

    /**
     * Derived lifecycle state. See ServiceLifecycleState for the eight
     * named states. Order of evaluation matters: retirement wins over
     * suspension wins over publication wins over uat wins over
     * configuration.
     */
    public function lifecycle(): string
    {
        if ($this->isRetired()) {
            return ServiceLifecycleState::RETIRED;
        }
        if ($this->isSuspended()) {
            return ServiceLifecycleState::SUSPENDED;
        }
        if ($this->isPublished()) {
            return ServiceLifecycleState::PUBLISHED;
        }
        if ($this->hasUatApproval()) {
            return ServiceLifecycleState::UAT_APPROVED;
        }
        if ($this->uat_status === 'PENDING') {
            return ServiceLifecycleState::AWAITING_UAT;
        }
        if ($this->schemaIsTechnicallyValid()) {
            return ServiceLifecycleState::TECHNICALLY_VALIDATED;
        }
        if ($this->schemaIsConfigured()) {
            return ServiceLifecycleState::CONFIGURED;
        }
        return ServiceLifecycleState::DRAFT;
    }

    /**
     * "Configured" means the row exists with a schema JSON that at least
     * declares the four top-level sections. Deeper structural checks are
     * part of the "technically validated" step.
     */
    private function schemaIsConfigured(): bool
    {
        $schema = $this->schema ?? [];
        return array_key_exists('workflow', $schema)
            && array_key_exists('fields', $schema)
            && array_key_exists('documents', $schema)
            && array_key_exists('fee', $schema);
    }

    /**
     * "Technically validated" means configured + workflow has at least one
     * stage + fee has type + all top-level keys are the expected types.
     */
    private function schemaIsTechnicallyValid(): bool
    {
        if (!$this->schemaIsConfigured()) {
            return false;
        }
        $schema = $this->schema;
        $stages = $schema['workflow']['stages'] ?? null;
        if (!is_array($stages) || $stages === []) {
            return false;
        }
        $fee = $schema['fee'] ?? [];
        if (!is_array($fee) || empty($fee['type'])) {
            return false;
        }
        return true;
    }
}
