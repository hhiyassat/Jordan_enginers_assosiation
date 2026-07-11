<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IntegrationCycle
 *
 * Tracks one round-trip with Nashmi:
 *   1. Nashmi sends requirements (inbound)
 *   2. ESP team builds the service
 *   3. ESP notifies Nashmi that code is done (outbound)
 *   4. Nashmi sends reviewer/tester/QA feedback (inbound)
 *   5. Cycle closes or loops back to #2
 */
class IntegrationCycle extends Model
{
    protected $fillable = [
        'cycle_ref',
        'service_name',
        'requirements_source',
        'requirements_file_path',
        'requirements_meta',
        'status',
        'nashmi_project_id',
        'code_summary',
        'feedback',
        'notes',
        'requirements_received_at',
        'code_done_notified_at',
        'feedback_received_at',
    ];

    protected function casts(): array
    {
        return [
            'requirements_meta'        => 'array',
            'code_summary'             => 'array',
            'feedback'                 => 'array',
            'requirements_received_at' => 'datetime',
            'code_done_notified_at'    => 'datetime',
            'feedback_received_at'     => 'datetime',
        ];
    }

    /** Allowed status transitions */
    public const TRANSITIONS = [
        'requirements_received' => ['code_done'],
        'code_done'             => ['feedback_received'],
        'feedback_received'     => ['code_done', 'closed'],
        'closed'                => [],
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
