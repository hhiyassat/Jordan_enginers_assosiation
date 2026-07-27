<?php

namespace Modules\JeaServices\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * DATA-005: qr_token is SHA-256 HMAC-signed.
 * BR-006: Certificate only exists after full workflow approval.
 *
 * @property int $id
 * @property int $application_id
 * @property int $organization_id
 * @property int $issued_to
 * @property int $issued_by
 * @property string $certificate_number
 * @property string $qr_token
 * @property string $status
 * @property Carbon $issued_date
 * @property Carbon $expiry_date
 * @property array<string, mixed>|null $cert_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Application|null $application
 * @property User|null $issuedTo
 * @property User|null $issuedBy
 */
class Certificate extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'application_id', 'organization_id', 'issued_to', 'issued_by',
        'certificate_number', 'qr_token', 'status', 'issued_date', 'expiry_date', 'cert_data',
    ];

    protected $casts = [
        'cert_data' => 'array',
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
    // organization() provided by BelongsToOrganization trait
}
