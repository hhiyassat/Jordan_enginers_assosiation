<?php

namespace Integrations\Nashmi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Integrations\Nashmi\Models\IntegrationCycle;
use Integrations\Nashmi\Services\NashmiIntegrationService;

class ProcessNashmiOutboundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $cycleId,
        public array $payload,
    ) {}

    public function handle(NashmiIntegrationService $nashmiService): void
    {
        $cycle = IntegrationCycle::find($this->cycleId);
        if (! $cycle) {
            Log::warning('ProcessNashmiOutboundJob: Cycle not found', ['cycle_id' => $this->cycleId]);
            return;
        }

        $nashmiService->notifyCodeDone($cycle, $this->payload['summary'] ?? '');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNashmiOutboundJob failed', [
            'cycle_id' => $this->cycleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
