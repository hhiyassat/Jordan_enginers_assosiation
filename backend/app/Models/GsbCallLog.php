<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GSB Call Log
 *
 * Mandatory audit record for every Government Service Bus API call.
 * MODEE Annex 4.15 §4.5 rule 10 — logged fields: URL, timestamp, source IP, user ID.
 * MODEE Annex 4.15 §4.9.3 — retained for minimum 180 days.
 *
 * @property int         $id
 * @property string      $gsb_endpoint
 * @property string      $http_method
 * @property string      $source_ip
 * @property string|null $user_identifier
 * @property int|null    $user_id
 * @property string|null $service_name
 * @property string|null $operation
 * @property bool        $is_citizen_data
 * @property bool        $otp_verified
 * @property int|null    $response_status
 * @property bool        $success
 * @property string|null $error_code
 * @property bool        $ip_whitelisted
 * @property bool        $bulk_request
 * @property bool|null   $committee_approved
 * @property int|null    $duration_ms
 * @property \Carbon\Carbon $logged_at
 */
class GsbCallLog extends Model
{
    protected $table = 'gsb_call_logs';

    protected $fillable = [
        'gsb_endpoint',
        'http_method',
        'source_ip',
        'user_identifier',
        'user_id',
        'service_name',
        'operation',
        'is_citizen_data',
        'otp_verified',
        'response_status',
        'success',
        'error_code',
        'ip_whitelisted',
        'bulk_request',
        'committee_approved',
        'duration_ms',
        'logged_at',
    ];

    protected $casts = [
        'is_citizen_data'    => 'boolean',
        'otp_verified'       => 'boolean',
        'success'            => 'boolean',
        'ip_whitelisted'     => 'boolean',
        'bulk_request'       => 'boolean',
        'committee_approved' => 'boolean',
        'logged_at'          => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Records eligible for pruning (older than 180 days) */
    public function scopeExpired($query)
    {
        return $query->where('logged_at', '<', now()->subDays(180));
    }

    /** Citizen data access records */
    public function scopeCitizenData($query)
    {
        return $query->where('is_citizen_data', true);
    }

    /** Failed calls only */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /** Calls from non-whitelisted IPs — anomaly flag (§4.5 rule 11) */
    public function scopeUnauthorizedSource($query)
    {
        return $query->where('ip_whitelisted', false);
    }
}
