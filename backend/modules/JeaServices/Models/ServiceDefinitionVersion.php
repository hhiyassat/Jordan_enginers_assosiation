<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * SG-03 · Immutable service-definition version snapshot.
 *
 * Immutability is enforced in the `saving` observer: once a version has
 * status='PUBLISHED', no attribute may be modified except a transition to
 * SUPERSEDED or RETIRED via the explicit lifecycle methods.
 *
 * @property int                              $id
 * @property int                              $service_definition_id
 * @property string                           $version_identifier
 * @property array<string, mixed>             $schema_snapshot
 * @property string                           $schema_hash
 * @property string                           $status
 * @property \Illuminate\Support\Carbon|null  $effective_from
 * @property \Illuminate\Support\Carbon|null  $effective_to
 * @property int|null                         $created_by
 * @property int|null                         $approved_by
 * @property string|null                      $approval_reference
 * @property string|null                      $approval_notes
 * @property \Illuminate\Support\Carbon|null  $published_at
 * @property int|null                         $published_by
 * @property int|null                         $supersedes_version_id
 */
class ServiceDefinitionVersion extends Model
{
    public const STATUS_DRAFT      = 'DRAFT';
    public const STATUS_PUBLISHED  = 'PUBLISHED';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';
    public const STATUS_RETIRED    = 'RETIRED';

    /** columns that may change on a PUBLISHED version (all others are frozen). */
    private const ALLOWED_UPDATES_AFTER_PUBLISH = [
        'status', 'effective_to', 'supersedes_version_id', 'updated_at',
    ];

    protected $fillable = [
        'service_definition_id', 'version_identifier',
        'schema_snapshot', 'schema_hash', 'status',
        'effective_from', 'effective_to',
        'created_by', 'approved_by', 'approval_reference', 'approval_notes',
        'published_at', 'published_by',
        'supersedes_version_id',
    ];

    protected $casts = [
        'schema_snapshot' => 'array',
        'effective_from'  => 'datetime',
        'effective_to'    => 'datetime',
        'published_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if (! $version->exists) {
                return; // insert is fine
            }
            $original = $version->getOriginal();
            if (($original['status'] ?? null) !== self::STATUS_PUBLISHED) {
                return; // pre-publish edits allowed
            }
            $dirty = array_diff(array_keys($version->getDirty()), self::ALLOWED_UPDATES_AFTER_PUBLISH);
            if ($dirty !== []) {
                throw new RuntimeException(sprintf(
                    'Cannot modify published ServiceDefinitionVersion #%d: attempt to change frozen columns [%s]. Only [%s] may change after publish (lifecycle transitions).',
                    $version->id,
                    implode(',', $dirty),
                    implode(',', self::ALLOWED_UPDATES_AFTER_PUBLISH),
                ));
            }
        });
    }

    /** @return BelongsTo<ServiceDefinition, $this> */
    public function serviceDefinition(): BelongsTo
    {
        return $this->belongsTo(ServiceDefinition::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Compute the canonical SHA-256 hash of a schema payload. Keys are sorted
     * recursively so semantically-equivalent JSON produces the same hash.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function computeSchemaHash(array $schema): string
    {
        return hash('sha256', self::canonicalize($schema));
    }

    /** @param mixed $value */
    private static function canonicalize($value): string
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                ksort($value);
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = json_encode($k) . ':' . self::canonicalize($v);
                }
                return '{' . implode(',', $parts) . '}';
            }
            $parts = array_map([self::class, 'canonicalize'], $value);
            return '[' . implode(',', $parts) . ']';
        }
        return (string) json_encode($value);
    }
}
