<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ServiceDefinition
 *
 * BR-001: schema JSON column is the source of truth for the entire service.
 * BR-005: workflow stages are read from schema, never hardcoded.
 */
class ServiceDefinition extends Model
{
    protected $fillable = [
        'organization_id', 'code', 'name_ar', 'name_en',
        'description_ar', 'description_en', 'currency', 'schema', 'status',
    ];

    protected $casts = ['schema' => 'array'];

    // ── Relationships ─────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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
