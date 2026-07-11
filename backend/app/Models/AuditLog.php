<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/**
 * AuditLog — append-only event log.
 *
 * DATA-003: No UPDATE or DELETE — this model has no updated_at.
 * WF-003: Every state mutation calls AuditLog::record().
 * SEC-007: input_snapshot must have sensitive fields redacted before calling record().
 *
 * EDA B-9: Effect recorded — every transition writes one row.
 */
class AuditLog extends Model
{
    const UPDATED_AT = null; // append-only

    protected $fillable = [
        'organization_id', 'user_id', 'auditable_type', 'auditable_id',
        'action', 'rule_id', 'from_status', 'to_status',
        'input_snapshot', 'extra',
        'is_manual_override', 'override_authorized_by', 'override_reason',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'input_snapshot'      => 'array',
        'extra'               => 'array',
        'is_manual_override'  => 'boolean',
    ];

    /** SEC-007: Fields redacted from input_snapshot before storage */
    private const SENSITIVE_FIELDS = [
        'national_id', 'owner_national_id', 'password', 'password_confirmation',
        'otp', 'otp_code', 'token', 'qr_token',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // ── Factory method ────────────────────────────────────────────────

    /**
     * WF-003: Standard record factory.
     *
     * Usage:
     *   AuditLog::record(
     *       user:    $actor,
     *       subject: $application,
     *       action:  'application.submitted',
     *       extra: ['rule_id' => 'ESP-WF-001', 'from_status' => 'draft', 'to_status' => 'submitted']
     *   );
     *
     * Call INSIDE a DB::transaction() to ensure atomicity.
     */
    public static function record(
        User     $user,
        Model    $subject,
        string   $action,
        array    $extra = [],
        ?Request $request = null,
    ): self {
        $snapshot = isset($extra['input_snapshot'])
            ? self::redact($extra['input_snapshot'])
            : null;

        unset($extra['input_snapshot']);

        return self::create([
            'organization_id'        => $user->organization_id,
            'user_id'                => $user->id,
            'auditable_type'         => get_class($subject),
            'auditable_id'           => $subject->getKey(),
            'action'                 => $action,
            'rule_id'                => $extra['rule_id'] ?? null,
            'from_status'            => $extra['from_status'] ?? null,
            'to_status'              => $extra['to_status'] ?? null,
            'input_snapshot'         => $snapshot,
            'extra'                  => array_diff_key($extra, array_flip([
                'rule_id', 'from_status', 'to_status',
            ])),
            'is_manual_override'     => $extra['is_manual_override'] ?? false,
            'override_authorized_by' => $extra['override_authorized_by'] ?? null,
            'override_reason'        => $extra['override_reason'] ?? null,
            'ip_address'             => $request?->ip(),
            'user_agent'             => $request?->userAgent(),
        ]);
    }

    /** SEC-007: Replace sensitive field values with [REDACTED] */
    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::SENSITIVE_FIELDS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }
        return $data;
    }
}
