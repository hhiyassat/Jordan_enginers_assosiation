<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ServiceDefinition
 *
 * BR-001: schema JSON column is the source of truth for the entire service.
 * BR-005: workflow stages are read from schema, never hardcoded.
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
    ];

    protected $casts = [
        'schema'    => 'array',
        'base_fee'  => 'decimal:2',
        'sla_hours' => 'integer',
        'phase'     => 'integer',
        'is_locked' => 'boolean',
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

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    // ── Schema accessors (typed for engine use) ────────────────────────

    public function getWorkflowStages(): array
    {
        return $this->schema['workflow']['stages'] ?? [];
    }

    public function getStage(string $stageId): ?array
    {
        foreach ($this->getWorkflowStages() as $stage) {
            if ($stage['id'] === $stageId) {
                return $stage;
            }
        }
        return null;
    }

    public function getFirstStage(): ?array
    {
        return $this->getWorkflowStages()[0] ?? null;
    }

    public function getFields(): array
    {
        return $this->schema['fields'] ?? [];
    }

    public function getDocuments(): array
    {
        return $this->schema['documents'] ?? [];
    }

    public function getFeeConfig(): array
    {
        return $this->schema['fee'] ?? [];
    }

    public function getCertificateConfig(): array
    {
        return $this->schema['certificate'] ?? [];
    }
}
