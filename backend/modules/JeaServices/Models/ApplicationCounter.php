<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ApplicationCounter — per-service per-year sequence source.
 *
 * H-02. Companion to CertificateCounter. Row keys are
 * `(service_definition_id, year)`; `next_serial` is the number to be
 * consumed on the NEXT allocation call.
 *
 * Not tenant-scoped: services are shared catalog entities and their
 * sequence is meaningful across every organization that consumes
 * them (matching the pre-remediation shape of Application counter
 * queries that already used `withoutOrgScope`).
 */
class ApplicationCounter extends Model
{
    protected $fillable = ['service_definition_id', 'year', 'next_serial'];

    protected $casts = [
        'service_definition_id' => 'integer',
        'year'                  => 'integer',
        'next_serial'           => 'integer',
    ];
}
