<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PaymentCallback — CS-03.
 *
 * One row per verified gateway callback. Insertion is idempotent via the
 * unique constraint on `reference`; controllers use `insertOrIgnore`
 * (or catch a QueryException on unique-key clash) to fold duplicate
 * webhook deliveries into a single business-side effect.
 *
 * The model is intentionally minimal — it exists to persist the
 * dedup key + evidentiary payload, not to carry business logic.
 *
 * @property int                              $id
 * @property int|null                         $application_id
 * @property string                           $reference
 * @property float                            $amount
 * @property string                           $currency
 * @property \Illuminate\Support\Carbon|null  $settled_at
 * @property array<string, mixed>|null        $gateway_meta
 * @property \Illuminate\Support\Carbon       $received_at
 */
class PaymentCallback extends Model
{
    protected $fillable = [
        'application_id',
        'reference',
        'amount',
        'currency',
        'settled_at',
        'gateway_meta',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'float',
            'gateway_meta' => 'array',
            'settled_at'   => 'datetime',
            'received_at'  => 'datetime',
        ];
    }
}
