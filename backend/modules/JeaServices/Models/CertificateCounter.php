<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-(organization, year) monotonic counter used to allocate the serial
 * suffix on certificate numbers. Read + increment through a
 * SELECT ... FOR UPDATE inside issueCertificate's transaction — see
 * WorkflowEngine::allocateCertificateSerial().
 *
 * Not org-scoped via the trait: this table has NO relationship to the
 * OrganizationScope global scope because we look it up by explicit
 * (organization_id, year) in the WorkflowEngine, not through the query
 * builder chain that the trait wraps.
 */
class CertificateCounter extends Model
{
    protected $fillable = ['organization_id', 'year', 'next_serial'];

    protected $casts = [
        'year'        => 'integer',
        'next_serial' => 'integer',
    ];
}
