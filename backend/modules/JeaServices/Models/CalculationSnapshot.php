<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * SG-04 · immutable-when-committed record of a calculator's inputs and outputs.
 *
 * DRAFT snapshots may be overwritten by CalculationSnapshotWriter. SUBMIT and
 * MANUAL_RECALC snapshots are immutable after insertion — enforced by the
 * saving observer.
 *
 * @property int                              $id
 * @property int                              $application_id
 * @property int                              $rule_version_id
 * @property string                           $purpose
 * @property array<string, mixed>             $inputs
 * @property array<string, mixed>             $outputs
 * @property array<string, mixed>|null        $intermediate_values
 * @property array<int, string>|null          $warnings
 * @property array<int, string>|null          $open_decisions
 * @property int|null                         $superseded_snapshot_id
 * @property int|null                         $calculated_by
 * @property \Illuminate\Support\Carbon       $calculated_at
 */
class CalculationSnapshot extends Model
{
    public const PURPOSE_DRAFT         = 'DRAFT';
    public const PURPOSE_SUBMIT        = 'SUBMIT';
    public const PURPOSE_MANUAL_RECALC = 'MANUAL_RECALC';

    protected $fillable = [
        'application_id', 'rule_version_id', 'purpose',
        'inputs', 'outputs', 'intermediate_values',
        'warnings', 'open_decisions',
        'superseded_snapshot_id', 'calculated_by', 'calculated_at',
    ];

    protected $casts = [
        'inputs'              => 'array',
        'outputs'             => 'array',
        'intermediate_values' => 'array',
        'warnings'            => 'array',
        'open_decisions'      => 'array',
        'calculated_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $s): void {
            if (! $s->exists) {
                return; // insert is fine
            }
            $original = $s->getOriginal();
            if (($original['purpose'] ?? null) === self::PURPOSE_DRAFT) {
                return; // draft overwrites allowed
            }
            $dirty = array_keys($s->getDirty());
            if ($dirty !== []) {
                throw new RuntimeException(sprintf(
                    'Cannot modify committed CalculationSnapshot #%d (purpose=%s). Immutable after insert.',
                    $s->id,
                    $original['purpose'] ?? '(unknown)',
                ));
            }
        });
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
