<?php

declare(strict_types=1);

namespace Integrations\Nashmi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Integrations\Nashmi\Models\IntegrationCycle;
use Integrations\Nashmi\Services\NashmiIntegrationService;

/**
 * ProcessNashmiOutboundJob — CS-02.
 *
 * Runs the outbound HTTP call to Nashmi's AI Manager off the request
 * thread. The controller dispatches this job after the cycle
 * transition is durable; the request returns 202 Accepted immediately
 * and this job handles retries + failure persistence.
 *
 * Idempotency: `WithoutOverlapping($cycleId)` prevents two workers
 * from racing on the same cycle. Retries are safe — Nashmi's endpoint
 * is a create-from-requirements POST which the cycle timeline can
 * absorb without duplicate side-effects on the ESP side (the cycle
 * status update is guarded by the controller's `canTransitionTo`
 * check before dispatch).
 */
class ProcessNashmiOutboundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry ceiling before the job is marked failed. */
    public int $tries = 3;

    /**
     * Backoff schedule in seconds between retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /** Kill switch — abort the individual attempt if the HTTP call hangs. */
    public int $timeout = 60;

    /** After this many total seconds the job is failed regardless of tries. */
    public int $retryUntil = 3600;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $cycleId,
        public array $payload,
        public ?string $correlationId = null,
    ) {
    }

    /**
     * Ensure two workers can't process the same cycle concurrently.
     * The lock is released after `handle()` returns (success or throw).
     *
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->cycleId))
                ->releaseAfter(60)
                ->expireAfter(120),
        ];
    }

    public function handle(NashmiIntegrationService $nashmiService): void
    {
        $cycle = IntegrationCycle::find($this->cycleId);
        if (! $cycle) {
            Log::channel('integration')->warning('ProcessNashmiOutboundJob: cycle not found', [
                'cycle_id'       => $this->cycleId,
                'correlation_id' => $this->correlationId,
            ]);
            return;
        }

        Log::channel('integration')->info('ProcessNashmiOutboundJob: dispatching to Nashmi', [
            'cycle_id'       => $cycle->id,
            'cycle_ref'      => $cycle->cycle_ref,
            'correlation_id' => $this->correlationId,
            'attempt'        => $this->attempts(),
        ]);

        $summary = $this->payload['summary'] ?? [];
        $result  = $nashmiService->notifyCodeDone($cycle, is_array($summary) ? $summary : []);

        if (empty($result['success'])) {
            // Throw so the queue retries with backoff. The last attempt lands in failed_jobs.
            throw new \RuntimeException(
                'Nashmi outbound call failed: ' . ($result['error'] ?? 'unknown')
            );
        }

        $cycle->refresh();
        $cycle->update([
            'status'                => 'code_done',
            'code_summary'          => array_merge(
                $cycle->code_summary ?? [],
                is_array($summary) ? $summary : []
            ),
            'nashmi_project_id'     => $result['data']['project']['id'] ?? $cycle->nashmi_project_id,
            'code_done_notified_at' => now(),
        ]);
    }

    // NB: `payload` is untyped array on the property so PHPStan doesn't
    // enforce a value type; the job hydrates from `$this->payload['summary']`
    // defensively via `is_array()` in `handle()`.

    public function failed(\Throwable $exception): void
    {
        Log::channel('integration')->error('ProcessNashmiOutboundJob failed permanently', [
            'cycle_id'       => $this->cycleId,
            'correlation_id' => $this->correlationId,
            'error'          => $exception->getMessage(),
        ]);
    }
}
