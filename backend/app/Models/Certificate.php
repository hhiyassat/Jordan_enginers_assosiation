<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DATA-005: qr_token is SHA-256 HMAC-signed.
 * BR-006: Certificate only exists after full workflow approval.
 *
 * @property int                            $id
 * @property string                         $certificate_number
 * @property string                         $qr_token
 * @property string                         $status
 * @property array<string, mixed>|null      $cert_data
 * @property \Illuminate\Support\Carbon|null $issued_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property Application|null               $application
 * @property User|null                      $issuedTo
 * @property User|null                      $issuedBy
 */
class Certificate extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'application_id', 'organization_id', 'issued_to', 'issued_by',
        'certificate_number', 'qr_token', 'status', 'issued_date', 'expiry_date', 'cert_data',
    ];

    protected $casts = [
        'cert_data'   => 'array',
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function issuedTo(): BelongsTo    { return $this->belongsTo(User::class, 'issued_to'); }
    public function issuedBy(): BelongsTo    { return $this->belongsTo(User::class, 'issued_by'); }
    // organization() provided by BelongsToOrganization trait
}
