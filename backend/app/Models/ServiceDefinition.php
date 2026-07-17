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
        'organization_id', 'code', 'parent_code', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'currency', 'base_fee', 'sla_hours',
        'schema', 'status',
    ];

    protected $casts = [
        'schema'    => 'array',
        'base_fee'  => 'decimal:2',
        'sla_hours' => 'integer',
    ];

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
