<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DATA-005: qr_token is SHA-256 HMAC-signed.
 * BR-006: Certificate only exists after full workflow approval.
 */
class Certificate extends Model
{
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
    public function organization(): BelongsTo{ return $this->belongsTo(Organization::class); }
}
